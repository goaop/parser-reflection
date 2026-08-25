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
use PHPUnit\Framework\TestCase;

/**
 * Proves the PHP 8.6 test scaffolding: the "FileWith*86.php" stub naming convention and the
 * "PHP_VERSION_ID >= 80600" guard convention used for runtime-only assertions.
 *
 * The static part of this test runs on every supported runtime, because the reflection engine
 * analyzes sources without loading them. Only the parity assertions against native reflection
 * require the stub to be loaded, and those are guarded by the PHP version check.
 */
class Php86ParsingTest extends TestCase
{
    public const STUB_FILE = '/Stub/FileWithClasses86.php';

    public const STUB_NAMESPACE = 'Go\ParserReflection\Stub';

    public const STUB_CLASS = 'Go\ParserReflection\Stub\ClassWithPhp86Members';

    public const STUB_ENUM = 'Go\ParserReflection\Stub\EnumWithPhp86Cases';

    private string $stubFileName;

    protected function setUp(): void
    {
        $resolvedFileName = stream_resolve_include_path(__DIR__ . self::STUB_FILE);
        $this->assertIsString($resolvedFileName, 'PHP 8.6 stub file should be available');

        $this->stubFileName = $resolvedFileName;

        // The stub file is not autoloadable by its class names, so the engine gets an explicit locator
        $stubFileName = $resolvedFileName;
        ReflectionEngine::init(new CallableLocator(
            static fn(string $className): false|string
                => str_starts_with($className, self::STUB_NAMESPACE . '\\') ? $stubFileName : false
        ));
    }

    protected function tearDown(): void
    {
        // Restores the default locator for the following tests, because some of them replace it
        ReflectionEngine::init(new ComposerLocator());
    }

    public function testStubFileIsParsed(): void
    {
        $reflectionFile = new ReflectionFile($this->stubFileName);

        $this->assertTrue($reflectionFile->isStrictMode());
        $this->assertTrue($reflectionFile->hasFileNamespace(self::STUB_NAMESPACE));
    }

    public function testStubClassesAreFound(): void
    {
        $reflectionNamespace = $this->getStubNamespace();
        $parsedClassNames    = array_keys($reflectionNamespace->getClasses());

        $this->assertContains(self::STUB_NAMESPACE . '\InterfaceWithPhp86Contract', $parsedClassNames);
        $this->assertContains(self::STUB_NAMESPACE . '\TraitWithPhp86Helpers', $parsedClassNames);
        $this->assertContains(self::STUB_NAMESPACE . '\AbstractClassWithPhp86Members', $parsedClassNames);
        $this->assertContains(self::STUB_CLASS, $parsedClassNames);
        $this->assertContains(self::STUB_ENUM, $parsedClassNames);
    }

    public function testStubClassIsReflectedStatically(): void
    {
        $parsedClass = $this->getStubNamespace()->getClass(self::STUB_CLASS);

        $this->assertSame(self::STUB_CLASS, $parsedClass->getName());
        $this->assertSame($this->stubFileName, $parsedClass->getFileName());
        $this->assertTrue($parsedClass->isFinal());
        $this->assertSame(self::STUB_NAMESPACE . '\AbstractClassWithPhp86Members', $parsedClass->getParentClass()->getName());
        $this->assertContains(self::STUB_NAMESPACE . '\InterfaceWithPhp86Contract', $parsedClass->getInterfaceNames());
        $this->assertSame('php86-class', $parsedClass->getConstant('LABEL'));
        $this->assertTrue($parsedClass->hasMethod('helperName'), 'Trait method should be inherited from the parent');

        $parsedParent = $this->getStubNamespace()->getClass(self::STUB_NAMESPACE . '\AbstractClassWithPhp86Members');
        $this->assertTrue($parsedParent->isAbstract());
        $this->assertSame([self::STUB_NAMESPACE . '\TraitWithPhp86Helpers'], $parsedParent->getTraitNames());

        $parsedMethod = $parsedClass->getMethod('combine');
        $this->assertSame('string', (string) $parsedMethod->getReturnType());
        $this->assertTrue($parsedMethod->isVariadic());
        $this->assertSame(
            ['first', 'second', 'rest'],
            array_map(
                static fn(\ReflectionParameter $parameter): string => $parameter->getName(),
                $parsedMethod->getParameters()
            )
        );
        $this->assertSame('int|float', (string) $parsedMethod->getParameters()[0]->getType());
    }

    public function testStubEnumIsReflectedStatically(): void
    {
        $parsedEnum = new ReflectionEnum(self::STUB_ENUM, $this->getStubNamespace()->getClass(self::STUB_ENUM)->getNode());

        $this->assertTrue($parsedEnum->isBacked());
        $this->assertSame('string', (string) $parsedEnum->getBackingType());
        $this->assertSame(
            ['First', 'Second'],
            array_map(
                static fn(\ReflectionEnumUnitCase $case): string => $case->getName(),
                $parsedEnum->getCases()
            )
        );
    }

    public function testStubIsAnalyzableWithoutLoadingOnOlderRuntimes(): void
    {
        if (PHP_VERSION_ID >= 80600) {
            $this->markTestSkipped('The PHP 8.6 stub is loaded by the parity data providers on this runtime');
        }

        $parsedClass = new ReflectionClass(self::STUB_CLASS);

        $this->assertSame(self::STUB_CLASS, $parsedClass->getName());
        $this->assertFalse(class_exists(self::STUB_CLASS, false), 'Stub class should not be loaded');
        $this->assertTrue($parsedClass->hasMethod('describe'));
    }

    /**
     * Demonstrates the "PHP_VERSION_ID >= 80600" guard convention for runtime-only assertions
     */
    public function testStubMatchesNativeReflectionOnPhp86(): void
    {
        if (PHP_VERSION_ID < 80600) {
            $this->markTestSkipped('Native reflection parity for the 8.6 stub requires PHP 8.6 runtime');
        }

        include_once $this->stubFileName;

        $parsedClass = $this->getStubNamespace()->getClass(self::STUB_CLASS);
        $nativeClass = new \ReflectionClass(self::STUB_CLASS);

        $this->assertSame($nativeClass->isFinal(), $parsedClass->isFinal());
        $this->assertSame($nativeClass->getParentClass()->getName(), $parsedClass->getParentClass()->getName());
        $this->assertSame($nativeClass->getInterfaceNames(), $parsedClass->getInterfaceNames());
        $this->assertSame($nativeClass->getTraitNames(), $parsedClass->getTraitNames());
        $this->assertSame($nativeClass->getConstants(), $parsedClass->getConstants());
        $this->assertSame(
            array_map(static fn(\ReflectionMethod $method): string => $method->getName(), $nativeClass->getMethods()),
            array_map(static fn(\ReflectionMethod $method): string => $method->getName(), $parsedClass->getMethods())
        );
        $this->assertSame(
            array_map(static fn(\ReflectionProperty $property): string => $property->getName(), $nativeClass->getProperties()),
            array_map(static fn(\ReflectionProperty $property): string => $property->getName(), $parsedClass->getProperties())
        );
    }

    private function getStubNamespace(): ReflectionFileNamespace
    {
        return (new ReflectionFile($this->stubFileName))->getFileNamespace(self::STUB_NAMESPACE);
    }
}
