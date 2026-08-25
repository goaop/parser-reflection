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
 * Tests for the smaller PHP 8.6 reflection items:
 *
 *  - the #[\Override] attribute that is allowed on class, interface and enum constants;
 *  - the __debugInfo() magic method that is allowed on enums (Debuggable Enums RFC);
 *  - the attribute API additions of the native \ReflectionAttribute class.
 *
 * Both stub files can be parsed by any runtime, because the engine analyzes sources without loading
 * them, but they can only be included by a PHP 8.6+ one. Therefore every assertion that needs the
 * stub to be loaded (native reflection parity) is guarded with a PHP_VERSION_ID >= 80600 check.
 */
class Php86MiscParityTest extends TestCase
{
    private const STUB_NAMESPACE = 'Go\ParserReflection\Stub';

    private const OVERRIDE_STUB_FILE = __DIR__ . '/Stub/FileWithOverrideConstants86.php';

    private const ENUM_STUB_FILE = __DIR__ . '/Stub/FileWithDebuggableEnums86.php';

    private const BASE_CONTRACT = self::STUB_NAMESPACE . '\BaseContractWithConstants86';

    private const EXTENDED_CONTRACT = self::STUB_NAMESPACE . '\ExtendedContractWithConstants86';

    private const OVERRIDING_CLASS = self::STUB_NAMESPACE . '\ClassWithOverriddenConstants86';

    private const OVERRIDING_ENUM = self::STUB_NAMESPACE . '\EnumWithOverriddenConstants86';

    private const CONSTANT_MARKER = self::STUB_NAMESPACE . '\ClassConstantMarker86';

    private const PURE_ENUM = self::STUB_NAMESPACE . '\PureDebuggableEnum86';

    private const BACKED_ENUM = self::STUB_NAMESPACE . '\BackedDebuggableEnum86';

    private const PLAIN_ENUM = self::STUB_NAMESPACE . '\PlainEnumWithoutDebugInfo86';

    /**
     * Expected attribute names for every constant of the stub classes, keyed by "Class::CONSTANT"
     *
     * @var array<string, list<string>>
     */
    private const EXPECTED_CONSTANT_ATTRIBUTES = [
        self::EXTENDED_CONTRACT . '::LEVEL' => [\Override::class],
        self::OVERRIDING_CLASS . '::PLAIN'  => [],
        self::OVERRIDING_CLASS . '::TAG'    => [\Override::class],
        self::OVERRIDING_CLASS . '::WEIGHT' => [\Override::class, self::CONSTANT_MARKER],
        self::OVERRIDING_CLASS . '::KIND'   => [\Override::class, self::CONSTANT_MARKER, self::CONSTANT_MARKER],
        self::OVERRIDING_ENUM . '::LEVEL'   => [\Override::class],
        self::OVERRIDING_ENUM . '::KIND'    => [\Override::class, self::CONSTANT_MARKER],
        self::OVERRIDING_ENUM . '::OWN'     => [],
    ];

    /**
     * Maps every class of the stub files to the file that declares it, for the locator below
     *
     * @var array<class-string, string>
     */
    private const STUB_CLASS_MAP = [
        self::CONSTANT_MARKER                        => self::OVERRIDE_STUB_FILE,
        self::BASE_CONTRACT                          => self::OVERRIDE_STUB_FILE,
        self::EXTENDED_CONTRACT                      => self::OVERRIDE_STUB_FILE,
        self::STUB_NAMESPACE . '\AbstractClassWithConstants86' => self::OVERRIDE_STUB_FILE,
        self::OVERRIDING_CLASS                       => self::OVERRIDE_STUB_FILE,
        self::OVERRIDING_ENUM                        => self::OVERRIDE_STUB_FILE,
        self::STUB_NAMESPACE . '\DebuggableEnumContract86' => self::ENUM_STUB_FILE,
        self::PURE_ENUM                              => self::ENUM_STUB_FILE,
        self::BACKED_ENUM                            => self::ENUM_STUB_FILE,
        self::PLAIN_ENUM                             => self::ENUM_STUB_FILE,
    ];

    protected function setUp(): void
    {
        // Both stub files hold several classes at once, so they can not be resolved by the PSR-4 rules
        ReflectionEngine::init(new CallableLocator(
            static fn(string $className): false|string => self::STUB_CLASS_MAP[$className] ?? false
        ));
    }

    protected function tearDown(): void
    {
        // Restores the default locator for the following tests, because this one replaces it
        ReflectionEngine::init(new ComposerLocator());
    }

    /**
     * The #[\Override] attribute on class, interface and enum constants is resolved from the AST alone
     */
    public function testOverrideAttributeOnConstantsIsResolvedWithoutLoading(): void
    {
        foreach (self::EXPECTED_CONSTANT_ATTRIBUTES as $constantReference => $expectedAttributeNames) {
            [$className, $constantName] = explode('::', $constantReference);

            $parsedConstant = new ReflectionClassConstant($className, $constantName);
            $attributes     = $parsedConstant->getAttributes();

            $this->assertSame(
                $expectedAttributeNames,
                array_map(static fn(ReflectionAttribute $attribute): string => $attribute->getName(), $attributes),
                'Attributes of ' . $constantReference . ' should be resolved from the AST'
            );
        }
    }

    /**
     * The #[\Override] attribute can be combined with the userland ones, in any attribute group
     */
    public function testOverrideAttributeCombinedWithOtherAttributes(): void
    {
        $singleGroupConstant = new ReflectionClassConstant(self::OVERRIDING_CLASS, 'WEIGHT');
        $overrideAttributes  = $singleGroupConstant->getAttributes(\Override::class);
        $markerAttributes    = $singleGroupConstant->getAttributes(self::CONSTANT_MARKER);

        $this->assertCount(1, $overrideAttributes);
        $this->assertSame([], $overrideAttributes[0]->getArguments());
        $this->assertFalse($overrideAttributes[0]->isRepeated());

        $this->assertCount(1, $markerAttributes);
        $this->assertSame(['tag' => 'weight', 'priority' => 5], $markerAttributes[0]->getArguments());
        $this->assertFalse($markerAttributes[0]->isRepeated());

        $multiGroupConstant = new ReflectionClassConstant(self::OVERRIDING_CLASS, 'KIND');
        $repeatedAttributes = $multiGroupConstant->getAttributes(self::CONSTANT_MARKER);

        $this->assertCount(1, $multiGroupConstant->getAttributes(\Override::class));
        $this->assertFalse($multiGroupConstant->getAttributes(\Override::class)[0]->isRepeated());
        $this->assertCount(2, $repeatedAttributes);
        $this->assertSame(['first'], $repeatedAttributes[0]->getArguments());
        $this->assertSame(['second', 'priority' => 2], $repeatedAttributes[1]->getArguments());
        $this->assertTrue($repeatedAttributes[0]->isRepeated());
        $this->assertTrue($repeatedAttributes[1]->isRepeated());

        // Every attribute keeps the AST node it was built from
        foreach ([...$overrideAttributes, ...$markerAttributes, ...$repeatedAttributes] as $attribute) {
            $this->assertInstanceOf(\PhpParser\Node\Attribute::class, $attribute->getNode());
        }
    }

    /**
     * Filtering by the attribute name should work for the constants of interfaces and enums too
     */
    public function testOverrideAttributeFilteringOnInterfaceAndEnumConstants(): void
    {
        $interfaceConstant = new ReflectionClassConstant(self::EXTENDED_CONTRACT, 'LEVEL');
        $this->assertCount(1, $interfaceConstant->getAttributes(\Override::class));
        $this->assertSame('extended', $interfaceConstant->getValue());
        $this->assertSame(self::EXTENDED_CONTRACT, $interfaceConstant->getDeclaringClass()->getName());

        $enumConstant = new ReflectionClassConstant(self::OVERRIDING_ENUM, 'KIND');
        $this->assertCount(1, $enumConstant->getAttributes(\Override::class));
        $this->assertCount(1, $enumConstant->getAttributes(self::CONSTANT_MARKER));
        $this->assertCount(0, $enumConstant->getAttributes(\Deprecated::class));
        $this->assertSame('enum-kind', $enumConstant->getValue());

        // Enum cases are reported as class constants, but they carry no attributes here
        $enumCase = new ReflectionClassConstant(self::OVERRIDING_ENUM, 'First');
        $this->assertTrue($enumCase->isEnumCase());
        $this->assertSame([], $enumCase->getAttributes());

        $this->assertFalse(
            class_exists(self::OVERRIDING_ENUM, false),
            'Enum with overridden constants should not be loaded by the static analysis'
        );
    }

    /**
     * Enums may declare the __debugInfo() magic method since PHP 8.6, it is an ordinary method for reflection
     */
    public function testDebugInfoMethodOnEnumsIsResolvedWithoutLoading(): void
    {
        foreach ([self::PURE_ENUM, self::BACKED_ENUM] as $enumName) {
            $parsedEnum = new ReflectionClass($enumName);

            $this->assertTrue($parsedEnum->hasMethod('__debugInfo'), $enumName . ' should have __debugInfo()');
            $this->assertContains(
                '__debugInfo',
                array_map(static fn(\ReflectionMethod $method): string => $method->getName(), $parsedEnum->getMethods()),
                $enumName . '::getMethods() should list __debugInfo()'
            );

            $parsedMethod = $parsedEnum->getMethod('__debugInfo');
            $this->assertTrue($parsedMethod->isPublic());
            $this->assertFalse($parsedMethod->isStatic());
            $this->assertSame(0, $parsedMethod->getNumberOfParameters());
            $this->assertSame('array', (string) $parsedMethod->getReturnType());
            $this->assertSame($enumName, $parsedMethod->getDeclaringClass()->getName());
        }

        $this->assertFalse((new ReflectionClass(self::PLAIN_ENUM))->hasMethod('__debugInfo'));
        $this->assertFalse(
            enum_exists(self::PURE_ENUM, false),
            'Enum with __debugInfo() should not be loaded by the static analysis'
        );
    }

    /**
     * The dedicated ReflectionEnum should report the __debugInfo() method next to the enum specifics
     */
    public function testDebugInfoMethodIsReportedByReflectionEnum(): void
    {
        $parsedEnum = new ReflectionEnum(self::BACKED_ENUM);

        $this->assertTrue($parsedEnum->isBacked());
        $this->assertSame('int', (string) $parsedEnum->getBackingType());
        $this->assertSame(
            ['Low', 'High'],
            array_map(static fn(\ReflectionEnumUnitCase $case): string => $case->getName(), $parsedEnum->getCases())
        );
        $this->assertTrue($parsedEnum->hasMethod('__debugInfo'));
        $this->assertTrue($parsedEnum->hasMethod('describe'));
        $this->assertTrue($parsedEnum->getMethod('describe')->isStatic());
        $this->assertContains(self::STUB_NAMESPACE . '\DebuggableEnumContract86', $parsedEnum->getInterfaceNames());
    }

    /**
     * PHP 8.6 adds the name-related methods to the native \ReflectionAttribute, they operate on the internal
     * attribute structure that is never initialized for the parsed reflection, hence the own implementation
     */
    public function testAttributeNameMethodsAreImplementedForParsedAttributes(): void
    {
        $overrideAttribute = (new ReflectionClassConstant(self::OVERRIDING_CLASS, 'TAG'))->getAttributes()[0];

        $this->assertSame(\Override::class, $overrideAttribute->getName());
        $this->assertSame('Override', $overrideAttribute->getShortName());
        $this->assertSame('', $overrideAttribute->getNamespaceName());
        $this->assertFalse($overrideAttribute->inNamespace());

        $markerAttribute = (new ReflectionClassConstant(self::OVERRIDING_CLASS, 'WEIGHT'))
            ->getAttributes(self::CONSTANT_MARKER)[0];

        $this->assertSame(self::CONSTANT_MARKER, $markerAttribute->getName());
        $this->assertSame('ClassConstantMarker86', $markerAttribute->getShortName());
        $this->assertSame(self::STUB_NAMESPACE, $markerAttribute->getNamespaceName());
        $this->assertTrue($markerAttribute->inNamespace());
    }

    /**
     * Audit of the attribute API additions: \ReflectionAttribute::getCurrent() was still in voting when the
     * 8.6 beta was released, so there is nothing to emulate for it yet
     */
    public function testAttributeApiAdditionsAreEitherImplementedOrAbsent(): void
    {
        if (method_exists(\ReflectionAttribute::class, 'getCurrent')) {
            $this->markTestIncomplete(
                'ReflectionAttribute::getCurrent() has landed in this runtime and needs a parsed counterpart'
            );
        }

        $reflectionAttribute = new \ReflectionClass(ReflectionAttribute::class);
        foreach (get_class_methods(\ReflectionAttribute::class) as $nativeMethodName) {
            if ($nativeMethodName === '__toString') {
                // Not implemented for the parsed attributes yet, tracked separately from the 8.6 support
                continue;
            }
            $this->assertSame(
                ReflectionAttribute::class,
                $reflectionAttribute->getMethod($nativeMethodName)->getDeclaringClass()->getName(),
                'Method ' . $nativeMethodName . '() should be implemented for the parsed attributes'
            );
        }
    }

    /**
     * Static results for the #[\Override] constants should be equal to the native ones on PHP 8.6
     */
    public function testOverrideConstantsMatchNativeReflectionOnPhp86(): void
    {
        $this->skipWithoutPhp86();
        include_once self::OVERRIDE_STUB_FILE;

        foreach (array_keys(self::EXPECTED_CONSTANT_ATTRIBUTES) as $constantReference) {
            [$className, $constantName] = explode('::', $constantReference);

            $parsedConstant = new ReflectionClassConstant($className, $constantName);
            $nativeConstant = new \ReflectionClassConstant($className, $constantName);

            $parsedAttributes = $parsedConstant->getAttributes();
            $nativeAttributes = $nativeConstant->getAttributes();

            $this->assertSame(
                array_map(static fn(\ReflectionAttribute $attribute): string => $attribute->getName(), $nativeAttributes),
                array_map(static fn(ReflectionAttribute $attribute): string => $attribute->getName(), $parsedAttributes),
                'Attribute names of ' . $constantReference . ' should be equal to the native ones'
            );

            foreach ($nativeAttributes as $index => $nativeAttribute) {
                $parsedAttribute = $parsedAttributes[$index];

                $this->assertSame($nativeAttribute->getArguments(), $parsedAttribute->getArguments(), $constantReference);
                $this->assertSame($nativeAttribute->isRepeated(), $parsedAttribute->isRepeated(), $constantReference);
                $this->assertSame($nativeAttribute->getShortName(), $parsedAttribute->getShortName(), $constantReference);
                $this->assertSame($nativeAttribute->getNamespaceName(), $parsedAttribute->getNamespaceName(), $constantReference);
                $this->assertSame($nativeAttribute->inNamespace(), $parsedAttribute->inNamespace(), $constantReference);
            }

            $this->assertSame($nativeConstant->getValue(), $parsedConstant->getValue(), $constantReference);
            $this->assertSame(
                $nativeConstant->getDeclaringClass()->getName(),
                $parsedConstant->getDeclaringClass()->getName(),
                $constantReference
            );
            $this->assertSame($nativeConstant->__toString(), $parsedConstant->__toString(), $constantReference);
        }
    }

    /**
     * Static results for the debuggable enums should be equal to the native ones on PHP 8.6
     */
    public function testDebuggableEnumsMatchNativeReflectionOnPhp86(): void
    {
        $this->skipWithoutPhp86();
        include_once self::ENUM_STUB_FILE;

        foreach ([self::PURE_ENUM, self::BACKED_ENUM, self::PLAIN_ENUM] as $enumName) {
            $parsedEnum = new ReflectionEnum($enumName);
            $nativeEnum = new \ReflectionEnum($enumName);

            $this->assertSame($nativeEnum->hasMethod('__debugInfo'), $parsedEnum->hasMethod('__debugInfo'), $enumName);
            $this->assertSame(
                array_map(static fn(\ReflectionMethod $method): string => $method->getName(), $nativeEnum->getMethods()),
                array_map(static fn(\ReflectionMethod $method): string => $method->getName(), $parsedEnum->getMethods()),
                'Methods of ' . $enumName . ' should be equal to the native ones'
            );
            $this->assertSame(
                array_map(static fn(\ReflectionEnumUnitCase $case): string => $case->getName(), $nativeEnum->getCases()),
                array_map(static fn(\ReflectionEnumUnitCase $case): string => $case->getName(), $parsedEnum->getCases()),
                $enumName
            );

            if (!$nativeEnum->hasMethod('__debugInfo')) {
                continue;
            }

            $parsedMethod = $parsedEnum->getMethod('__debugInfo');
            $nativeMethod = $nativeEnum->getMethod('__debugInfo');

            $this->assertSame($nativeMethod->isPublic(), $parsedMethod->isPublic(), $enumName);
            $this->assertSame($nativeMethod->isStatic(), $parsedMethod->isStatic(), $enumName);
            $this->assertSame((string) $nativeMethod->getReturnType(), (string) $parsedMethod->getReturnType(), $enumName);
            $this->assertSame($nativeMethod->__toString(), $parsedMethod->__toString(), $enumName);
        }
    }

    private function skipWithoutPhp86(): void
    {
        if (PHP_VERSION_ID < 80600) {
            $this->markTestSkipped('Native reflection parity for this stub requires the PHP 8.6 runtime');
        }
    }
}
