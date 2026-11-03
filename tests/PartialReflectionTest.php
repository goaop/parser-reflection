<?php

declare(strict_types=1);
/**
 * Parser Reflection API
 *
 * @copyright Copyright 2026, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\ParserReflection;

use Go\ParserReflection\Locator\CallableLocator;
use Go\ParserReflection\Locator\ComposerLocator;
use InvalidArgumentException;
use PhpParser\Node\Stmt\Class_;
use PHPUnit\Framework\TestCase;

/**
 * A class whose ancestors can not be located is reflected partially with the partial policy
 *
 * @see https://github.com/goaop/parser-reflection/issues/251
 */
final class PartialReflectionTest extends TestCase
{
    private const string STUB_FILE         = __DIR__ . '/Stub/FileWithMissingAncestors.php';
    private const string NS                = 'Go\ParserReflection\Stub\MissingAncestors\\';
    private const string AVAILABLE_IFACE   = self::NS . 'AvailableInterface';
    private const string AVAILABLE_TRAIT   = self::NS . 'AvailableTrait';
    private const string PARTIAL_CLASS     = self::NS . 'ClassWithMissingAncestors';
    private const string CHILD_CLASS       = self::NS . 'ChildOfClassWithMissingAncestors';
    private const string PARTIAL_INTERFACE = self::NS . 'InterfaceWithMissingParent';
    private const string COMPLETE_CLASS    = self::NS . 'ClassWithAvailableAncestors';
    private const string MISSING_PARENT    = self::NS . 'Generated\MissingParent';
    private const string MISSING_INTERFACE = self::NS . 'Generated\MissingInterface';
    private const string MISSING_TRAIT     = self::NS . 'Generated\MissingTrait';

    protected function tearDown(): void
    {
        // Restores the default locator and the strict policy for the following tests
        ReflectionEngine::init(new ComposerLocator());
    }

    public function testStrictPolicyIsTheDefault(): void
    {
        ReflectionEngine::init(new ComposerLocator());

        $this->assertSame(MissingAncestorPolicy::Strict, ReflectionEngine::getMissingAncestorPolicy());
    }

    public function testPolicyIsSetByInitAndBySetter(): void
    {
        ReflectionEngine::init(new ComposerLocator(), null, MissingAncestorPolicy::Partial);
        $this->assertSame(MissingAncestorPolicy::Partial, ReflectionEngine::getMissingAncestorPolicy());

        ReflectionEngine::setMissingAncestorPolicy(MissingAncestorPolicy::Strict);
        $this->assertSame(MissingAncestorPolicy::Strict, ReflectionEngine::getMissingAncestorPolicy());

        ReflectionEngine::setMissingAncestorPolicy(MissingAncestorPolicy::Partial);
        ReflectionEngine::init(new ComposerLocator());
        $this->assertSame(MissingAncestorPolicy::Strict, ReflectionEngine::getMissingAncestorPolicy());
    }

    public function testStrictPolicyThrowsForAMissingAncestor(): void
    {
        $this->initEngine(MissingAncestorPolicy::Strict);
        $class = new ReflectionClass(self::PARTIAL_CLASS);

        try {
            $class->getMethods();
            $this->fail('A missing ancestor must be reported with the strict policy');
        } catch (ClassNotFoundException $e) {
            $this->assertInstanceOf(InvalidArgumentException::class, $e);
            $this->assertContains($e->className, [self::MISSING_PARENT, self::MISSING_INTERFACE, self::MISSING_TRAIT]);
            $this->assertSame('Class ' . $e->className . ' was not found by locator', $e->getMessage());
        }
    }

    public function testMissingAncestorsAreReportedWithTheStrictPolicyWithoutThrowing(): void
    {
        $this->initEngine(MissingAncestorPolicy::Strict);
        $class = new ReflectionClass(self::PARTIAL_CLASS);

        $this->assertTrue($class->isPartial());
        $this->assertSame([self::MISSING_PARENT, self::MISSING_INTERFACE, self::MISSING_TRAIT], $class->getMissingClassNames());
    }

    public function testOwnDeclarationsOfAPartialClassAreAvailable(): void
    {
        $this->initEngine(MissingAncestorPolicy::Partial);
        $class = new ReflectionClass(self::PARTIAL_CLASS);

        $this->assertTrue($class->isPartial());
        $this->assertSame([self::MISSING_PARENT, self::MISSING_INTERFACE, self::MISSING_TRAIT], $class->getMissingClassNames());

        $this->assertSame(self::PARTIAL_CLASS, $class->getName());
        $this->assertFalse($class->isFinal());
        $this->assertFalse($class->isAbstract());
        $this->assertSame(['ownMethod', 'availableInterfaceMethod', 'availableTraitMethod'], $this->names($class->getMethods()));
        $this->assertTrue($class->hasMethod('ownMethod'));
        $this->assertSame(['ownProperty'], $this->names($class->getProperties()));
        $this->assertSame(['OWN_CONSTANT' => 42, 'INTERFACE_CONSTANT' => 'interface'], $class->getConstants());
        $this->assertSame([self::NS . 'Marker'], array_map(
            static fn (\ReflectionAttribute $attribute): string => $attribute->getName(),
            $class->getAttributes()
        ));

        // Names of the ancestors as written stay available from the node
        $node = $class->getNode();
        $this->assertInstanceOf(Class_::class, $node);
        $this->assertSame(self::MISSING_PARENT, $node->extends?->getAttribute('resolvedName')?->toString());
    }

    public function testNamesOfMissingAncestorsAreKnownFromTheSource(): void
    {
        $this->initEngine(MissingAncestorPolicy::Partial);
        $class = new ReflectionClass(self::PARTIAL_CLASS);

        $this->assertSame(self::MISSING_PARENT, $class->getParentClassName());
        $this->assertSame([self::AVAILABLE_IFACE, self::MISSING_INTERFACE], $class->getInterfaceNames());
        $this->assertTrue($class->implementsInterface(self::AVAILABLE_IFACE));
        $this->assertTrue($class->implementsInterface(self::MISSING_INTERFACE));
        $this->assertFalse($class->implementsInterface(\Countable::class));
        $this->assertSame([self::AVAILABLE_TRAIT, self::MISSING_TRAIT], $class->getTraitNames());
        // Deprecated static access to the same names
        $this->assertSame(
            $class->getMissingClassNames(),
            ReflectionClass::collectMissingAncestorNames($class->getNode()),
        );
    }

    public function testParentClassNameIsReadFromTheSource(): void
    {
        foreach (MissingAncestorPolicy::cases() as $policy) {
            $this->initEngine($policy);

            $this->assertSame(\ArrayObject::class, (new ReflectionClass(self::COMPLETE_CLASS))->getParentClassName());
            $this->assertSame(self::PARTIAL_CLASS, (new ReflectionClass(self::CHILD_CLASS))->getParentClassName());
            $this->assertFalse((new ReflectionClass(self::AVAILABLE_IFACE))->getParentClassName());
        }
    }

    public function testMissingAncestorsAreLeftOutOfAPartialClass(): void
    {
        $this->initEngine(MissingAncestorPolicy::Partial);
        $class = new ReflectionClass(self::PARTIAL_CLASS);

        $this->assertFalse($class->getParentClass());
        $this->assertSame([self::AVAILABLE_IFACE], array_keys($class->getInterfaces()));
        $this->assertSame([self::AVAILABLE_TRAIT], array_keys($class->getTraits()));
        // The class declares these ancestors directly, so this is known from its source
        $this->assertTrue($class->isSubclassOf(self::MISSING_PARENT));
        $this->assertTrue($class->isSubclassOf(self::MISSING_INTERFACE));

        $method = $class->getMethod('availableInterfaceMethod');
        $this->assertTrue($method->hasPrototype());
        $this->assertSame(self::AVAILABLE_IFACE, $method->getPrototype()->getDeclaringClass()->getName());
        $this->assertFalse($class->getMethod('ownMethod')->hasPrototype());

        $this->assertStringContainsString('Class [ <user> class ' . self::PARTIAL_CLASS . ' implements ' . self::AVAILABLE_IFACE, (string) $class);
    }

    public function testMissingAncestorsOfAnAvailableAncestorAreReported(): void
    {
        $this->initEngine(MissingAncestorPolicy::Partial);
        $class = new ReflectionClass(self::CHILD_CLASS);

        $this->assertTrue($class->isPartial());
        $this->assertSame([self::MISSING_PARENT, self::MISSING_INTERFACE, self::MISSING_TRAIT], $class->getMissingClassNames());

        $parentClass = $class->getParentClass();
        $this->assertInstanceOf(ReflectionClass::class, $parentClass);
        $this->assertSame(self::PARTIAL_CLASS, $parentClass->getName());
        $this->assertTrue($parentClass->isPartial());
        $this->assertSame(
            ['childMethod', 'ownMethod', 'availableInterfaceMethod', 'availableTraitMethod'],
            $this->names($class->getMethods())
        );
        $this->assertTrue($class->isSubclassOf(self::PARTIAL_CLASS));
        // The interface missing for the parent class is named through it
        $this->assertSame([self::AVAILABLE_IFACE, self::MISSING_INTERFACE], $class->getInterfaceNames());
        $this->assertTrue($class->implementsInterface(self::MISSING_INTERFACE));
    }

    public function testInterfaceWithAMissingParentInterfaceIsPartial(): void
    {
        $this->initEngine(MissingAncestorPolicy::Partial);
        $interface = new ReflectionClass(self::PARTIAL_INTERFACE);

        $this->assertTrue($interface->isPartial());
        $this->assertSame([self::MISSING_INTERFACE], $interface->getMissingClassNames());
        $this->assertSame([self::AVAILABLE_IFACE], array_keys($interface->getInterfaces()));
        $this->assertSame([self::AVAILABLE_IFACE, self::MISSING_INTERFACE], $interface->getInterfaceNames());
        $this->assertSame(['availableInterfaceMethod'], $this->names($interface->getMethods()));
    }

    public function testClassWithAvailableAncestorsIsNotPartial(): void
    {
        foreach (MissingAncestorPolicy::cases() as $policy) {
            $this->initEngine($policy);
            $class = new ReflectionClass(self::COMPLETE_CLASS);

            $this->assertFalse($class->isPartial());
            $this->assertSame([], $class->getMissingClassNames());
            $this->assertSame(\ArrayObject::class, $class->getParentClass()->getName());
            $this->assertTrue($class->implementsInterface(\Countable::class));
        }
    }

    public function testClassesOfAParsedFileAreReflectedPartially(): void
    {
        $this->initEngine(MissingAncestorPolicy::Partial);
        $file  = new ReflectionFile(self::STUB_FILE);
        $class = $file->getFileNamespace(rtrim(self::NS, '\\'))->getClass(self::PARTIAL_CLASS);

        $this->assertTrue($class->isPartial());
        $this->assertSame(['ownMethod', 'availableInterfaceMethod', 'availableTraitMethod'], $this->names($class->getMethods()));
    }

    public function testMissingClassItselfStillThrowsWithThePartialPolicy(): void
    {
        $this->initEngine(MissingAncestorPolicy::Partial);

        $this->expectException(ClassNotFoundException::class);
        new ReflectionClass(self::MISSING_PARENT);
    }

    /**
     * Locates every class of the stub file, except the ones of the Generated namespace
     */
    private function initEngine(MissingAncestorPolicy $policy): void
    {
        $locator = new CallableLocator(static function (string $className): string|false {
            $isStubClass = str_starts_with($className, self::NS) && !str_starts_with($className, self::NS . 'Generated\\');

            return $isStubClass ? self::STUB_FILE : false;
        });
        ReflectionEngine::init($locator, null, $policy);
    }

    /**
     * @param array<\ReflectionMethod|\ReflectionProperty> $reflections
     *
     * @return list<string>
     */
    private function names(array $reflections): array
    {
        return array_values(array_map(static fn (\Reflector $reflection): string => $reflection->getName(), $reflections));
    }
}
