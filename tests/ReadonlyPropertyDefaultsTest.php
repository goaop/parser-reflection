<?php
declare(strict_types=1);
/**
 * Parser Reflection API
 *
 * @copyright Copyright 2025, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\ParserReflection;

use Go\ParserReflection\Locator\CallableLocator;
use Go\ParserReflection\Locator\ComposerLocator;
use PHPUnit\Framework\TestCase;

/**
 * Documents how static reflection handles readonly properties that declare a default value.
 *
 * The "Readonly Property Defaults" RFC (https://wiki.php.net/rfc/readonly_property_defaults) was
 * accepted for PHP 8.6, but its implementation landed in php-src after the `php-8.6.0beta1` tag,
 * therefore no released runtime accepts this syntax yet: even 8.6.0beta1 aborts with
 * "Readonly property X::$y cannot have default value" at compile time.
 *
 * PHP-Parser parses the syntax regardless, so the whole matrix can be verified statically today.
 * The stub file must therefore never be included or autoloaded, it is only parsed. Assertions that
 * require executing such a class are collected in the single parity test at the bottom, which stays
 * skipped until the runtime really accepts readonly defaults (detected by a sub-process probe,
 * because the failure is an uncatchable compile-time fatal error and cannot be probed via eval()).
 */
class ReadonlyPropertyDefaultsTest extends TestCase
{
    public const STUB_FILE = '/Stub/FileWithReadonlyDefaults86.php';

    public const STUB_NAMESPACE = 'Go\ParserReflection\Stub\\';

    public const STUB_CLASS = self::STUB_NAMESPACE . 'ClassWithReadonlyDefaults86';

    public const READONLY_CLASS = self::STUB_NAMESPACE . 'ReadonlyClassWithDefaults86';

    public const PROMOTED_CLASS = self::STUB_NAMESPACE . 'ClassWithPromotedReadonlyDefaults86';

    public const CHILD_CLASS = self::STUB_NAMESPACE . 'ChildWithReadonlyDefaults86';

    private string $stubFileName;

    protected function setUp(): void
    {
        $resolvedFileName = stream_resolve_include_path(__DIR__ . self::STUB_FILE);
        $this->assertIsString($resolvedFileName, 'PHP 8.6 readonly-defaults stub file should be available');

        $this->stubFileName = $resolvedFileName;
    }

    protected function tearDown(): void
    {
        // Restores the default locator for the following tests, because this one replaces it
        ReflectionEngine::init(new ComposerLocator());
    }

    public function testReadonlyPropertyWithScalarDefault(): void
    {
        $parsedProperty = $this->getParsedClass(self::STUB_CLASS)->getProperty('name');

        $this->assertTrue($parsedProperty->isReadOnly(), 'Property should be readonly');
        $this->assertFalse($parsedProperty->isPromoted(), 'Property should not be promoted');
        $this->assertTrue($parsedProperty->hasDefaultValue(), 'Readonly property should report its default value');
        $this->assertSame('create_books_table', $parsedProperty->getDefaultValue());
        $this->assertNull($parsedProperty->getDefaultValueExpression(), 'Plain scalar default is not a const expression');
        $this->assertSame(
            "Property [ public protected(set) readonly string \$name = 'create_books_table' ]\n",
            (string) $parsedProperty
        );
    }

    /**
     * Defaults must be resolved independently of the property visibility
     */
    public function testReadonlyPropertyDefaultsForEveryVisibility(): void
    {
        $parsedClass = $this->getParsedClass(self::STUB_CLASS);

        $protectedProperty = $parsedClass->getProperty('version');
        $this->assertTrue($protectedProperty->isProtected());
        $this->assertTrue($protectedProperty->hasDefaultValue());
        $this->assertSame(1, $protectedProperty->getDefaultValue());

        $privateProperty = $parsedClass->getProperty('ratio');
        $this->assertTrue($privateProperty->isPrivate());
        $this->assertTrue($privateProperty->hasDefaultValue());
        $this->assertSame(0.5, $privateProperty->getDefaultValue());

        $boolProperty = $parsedClass->getProperty('enabled');
        $this->assertTrue($boolProperty->hasDefaultValue());
        $this->assertTrue($boolProperty->getDefaultValue());

        $nullableProperty = $parsedClass->getProperty('nullableWithNull');
        $this->assertTrue($nullableProperty->hasDefaultValue(), 'Explicit null default is still a default value');
        $this->assertNull($nullableProperty->getDefaultValue());
    }

    /**
     * Constant expressions in readonly defaults are evaluated by NodeExpressionResolver
     */
    public function testReadonlyPropertyWithConstantExpressionDefault(): void
    {
        $parsedClass = $this->getParsedClass(self::STUB_CLASS);

        $sumProperty = $parsedClass->getProperty('constExprSum');
        $this->assertTrue($sumProperty->hasDefaultValue());
        $this->assertSame(14, $sumProperty->getDefaultValue());

        $concatProperty = $parsedClass->getProperty('classConstantConcat');
        $this->assertTrue($concatProperty->hasDefaultValue());
        $this->assertSame('migration_books', $concatProperty->getDefaultValue());
        $this->assertSame("self::PREFIX . 'books'", $concatProperty->getDefaultValueExpression());
    }

    public function testReadonlyPropertyWithArrayDefault(): void
    {
        $parsedProperty = $this->getParsedClass(self::STUB_CLASS)->getProperty('list');

        $this->assertTrue($parsedProperty->hasDefaultValue());
        $this->assertSame(['a', 'b'], $parsedProperty->getDefaultValue());
        $this->assertSame("['a', 'b']", $parsedProperty->getDefaultValueExpression());
    }

    /**
     * An enum case used as a readonly default is recognised as a constant expression.
     *
     * The concrete enum instance can not be materialised while the enum itself is not loaded,
     * this is a general limitation of static reflection for enum-case constants and is unrelated
     * to the readonly modifier, therefore only the expression is asserted here.
     */
    public function testReadonlyPropertyWithEnumCaseDefault(): void
    {
        $parsedProperty = $this->getParsedClass(self::STUB_CLASS)->getProperty('suit');

        $this->assertTrue($parsedProperty->isReadOnly());
        $this->assertTrue($parsedProperty->hasDefaultValue());
        $this->assertSame('ReadonlyDefaultSuit86::Spades', $parsedProperty->getDefaultValueExpression());
    }

    /**
     * A readonly property without a default must keep reporting "no default value"
     */
    public function testReadonlyPropertyWithoutDefault(): void
    {
        $parsedProperty = $this->getParsedClass(self::STUB_CLASS)->getProperty('noDefault');

        $this->assertTrue($parsedProperty->isReadOnly());
        $this->assertFalse($parsedProperty->hasDefaultValue());
        $this->assertNull($parsedProperty->getDefaultValue());
        // Must not fail with "typed property must not be accessed before initialization"
        $this->assertNull($parsedProperty->getDefaultValueExpression());
        $this->assertSame(
            "Property [ public protected(set) readonly string \$noDefault ]\n",
            (string) $parsedProperty
        );
    }

    public function testPrivateSetReadonlyPropertyWithDefault(): void
    {
        $parsedProperty = $this->getParsedClass(self::STUB_CLASS)->getProperty('privateSetWithDefault');

        $this->assertTrue($parsedProperty->isReadOnly());
        $this->assertTrue($parsedProperty->isPrivateSet());
        $this->assertTrue($parsedProperty->isFinal(), 'Property with private(set) is implicitly final');
        $this->assertTrue($parsedProperty->hasDefaultValue());
        $this->assertSame('restricted', $parsedProperty->getDefaultValue());
    }

    public function testGetDefaultPropertiesContainsReadonlyDefaults(): void
    {
        $defaultProperties = $this->getParsedClass(self::STUB_CLASS)->getDefaultProperties();

        $this->assertSame('create_books_table', $defaultProperties['name']);
        $this->assertSame(1, $defaultProperties['version']);
        $this->assertSame(0.5, $defaultProperties['ratio']);
        $this->assertTrue($defaultProperties['enabled']);
        $this->assertArrayHasKey('nullableWithNull', $defaultProperties);
        $this->assertNull($defaultProperties['nullableWithNull']);
        $this->assertSame(14, $defaultProperties['constExprSum']);
        $this->assertSame('migration_books', $defaultProperties['classConstantConcat']);
        $this->assertSame(['a', 'b'], $defaultProperties['list']);
        $this->assertSame('restricted', $defaultProperties['privateSetWithDefault']);

        $this->assertArrayNotHasKey(
            'noDefault',
            $defaultProperties,
            'Readonly property without default must be omitted, like an uninitialized typed property'
        );
    }

    /**
     * A `readonly class` marks every property readonly, defaults must be reported as well
     */
    public function testReadonlyClassPropertyDefaults(): void
    {
        $parsedClass = $this->getParsedClass(self::READONLY_CLASS);
        $this->assertTrue($parsedClass->isReadOnly());

        $parsedProperty = $parsedClass->getProperty('name');
        $this->assertTrue($parsedProperty->isReadOnly(), 'Property of a readonly class is readonly');
        $this->assertTrue($parsedProperty->hasDefaultValue());
        $this->assertSame('readonly_class_default', $parsedProperty->getDefaultValue());

        $this->assertSame(['name' => 'readonly_class_default'], $parsedClass->getDefaultProperties());
    }

    public function testInheritedAndTraitReadonlyDefaults(): void
    {
        $parsedClass       = $this->getParsedClass(self::CHILD_CLASS);
        $defaultProperties = $parsedClass->getDefaultProperties();

        $this->assertSame(7, $defaultProperties['own']);
        $this->assertSame('trait_default', $defaultProperties['fromTrait'], 'Default from a trait should be collected');
        $this->assertSame('parent_default', $defaultProperties['inherited'], 'Inherited default should be collected');
        $this->assertCount(3, $defaultProperties);

        $this->assertTrue($parsedClass->getProperty('fromTrait')->isReadOnly());
        $this->assertTrue($parsedClass->getProperty('inherited')->isReadOnly());
    }

    /**
     * A default value of a promoted parameter belongs to the parameter, not to the property.
     *
     * Native reflection reports `hasDefaultValue() === false` for promoted properties even when the
     * corresponding constructor parameter is optional (https://bugs.php.net/bug.php?id=81386), and the
     * readonly modifier does not change that.
     */
    public function testPromotedReadonlyParameterWithDefault(): void
    {
        $parsedClass = $this->getParsedClass(self::PROMOTED_CLASS);

        $parsedProperty = $parsedClass->getProperty('promotedReadonly');
        $this->assertTrue($parsedProperty->isPromoted());
        $this->assertTrue($parsedProperty->isReadOnly());
        $this->assertFalse($parsedProperty->hasDefaultValue(), 'Promoted property has no property-level default');
        $this->assertNull($parsedProperty->getDefaultValue());
        $this->assertNull($parsedProperty->getDefaultValueExpression());
        $this->assertSame([], $parsedClass->getDefaultProperties(), 'Promoted defaults are not class defaults');

        $constructor = $parsedClass->getConstructor();
        $this->assertNotNull($constructor);
        [$firstParameter, $secondParameter] = $constructor->getParameters();

        $this->assertTrue($firstParameter->isPromoted());
        $this->assertTrue($firstParameter->isDefaultValueAvailable());
        $this->assertSame('promoted_default', $firstParameter->getDefaultValue());
        $this->assertTrue($secondParameter->isDefaultValueAvailable());
        $this->assertSame(42, $secondParameter->getDefaultValue());
    }

    public function testStubClassesAreNeverLoaded(): void
    {
        $this->assertSame(self::STUB_CLASS, $this->getParsedClass(self::STUB_CLASS)->getName());

        foreach ([self::STUB_CLASS, self::READONLY_CLASS, self::PROMOTED_CLASS, self::CHILD_CLASS] as $className) {
            $this->assertFalse(class_exists($className, false), 'Stub class should not be loaded: ' . $className);
        }
    }

    /**
     * Parity against native reflection, pending an actual runtime that accepts readonly defaults.
     *
     * TODO: remove the skip once php-src ships the feature in a beta/RC build, the expectations below
     *       have to be re-verified against the final native semantics at that point.
     */
    public function testNativeParityForReadonlyDefaults(): void
    {
        if (!self::runtimeSupportsReadonlyDefaults()) {
            $this->markTestSkipped(
                'The current runtime rejects readonly properties with default values '
                . '(the RFC implementation landed after the php-8.6.0beta1 tag), native parity can not be verified yet'
            );
        }

        // @codeCoverageIgnoreStart
        require_once $this->stubFileName;

        $parsedClass = $this->getParsedClass(self::STUB_CLASS);
        $nativeClass = new \ReflectionClass(self::STUB_CLASS);

        foreach (['name', 'version', 'ratio', 'enabled', 'nullableWithNull', 'constExprSum', 'classConstantConcat', 'list', 'noDefault'] as $propertyName) {
            $parsedProperty = $parsedClass->getProperty($propertyName);
            $nativeProperty = $nativeClass->getProperty($propertyName);

            $this->assertSame(
                $nativeProperty->hasDefaultValue(),
                $parsedProperty->hasDefaultValue(),
                'hasDefaultValue() mismatch for $' . $propertyName
            );
            $this->assertSame(
                $nativeProperty->getDefaultValue(),
                $parsedProperty->getDefaultValue(),
                'getDefaultValue() mismatch for $' . $propertyName
            );
        }

        $nativeDefaults = $nativeClass->getDefaultProperties();
        $parsedDefaults = $parsedClass->getDefaultProperties();
        // The enum-case default can only be compared once the enum is loaded, which is the case here
        $this->assertSame($nativeDefaults, $parsedDefaults);
        // @codeCoverageIgnoreEnd
    }

    /**
     * Detects whether the current runtime compiles a readonly property with a default value.
     *
     * The rejection is an uncatchable compile-time fatal error, so it can not be probed with eval()
     * inside the running process, a short-lived child process is used instead.
     */
    private static function runtimeSupportsReadonlyDefaults(): bool
    {
        static $isSupported = null;

        if ($isSupported !== null) {
            return $isSupported;
        }

        if (!function_exists('exec') || PHP_BINARY === '') {
            return $isSupported = false;
        }

        $probeCode = 'class ReadonlyDefaultsProbe { public readonly int $probe = 1; } exit(0);';
        $command   = escapeshellarg(PHP_BINARY) . ' -n -r ' . escapeshellarg($probeCode) . ' 2>&1';

        exec($command, $output, $exitCode);

        return $isSupported = ($exitCode === 0);
    }

    /**
     * Reflects a stub class without triggering autoloading of the PHP 8.6 source
     *
     * @param class-string<object> $className
     */
    private function getParsedClass(string $className): ReflectionClass
    {
        $stubFileName = $this->stubFileName;
        $locator      = new CallableLocator(
            static fn(string $classNameToLocate): false|string
                => str_starts_with($classNameToLocate, self::STUB_NAMESPACE) && str_ends_with($classNameToLocate, '86')
                    ? $stubFileName
                    : false
        );
        ReflectionEngine::init($locator);

        return new ReflectionClass($className);
    }
}
