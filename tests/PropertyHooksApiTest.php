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
 * Tests for the PHP 8.4 property API that is inherited from the internal reflection
 *
 * @see \Go\ParserReflection\ReflectionProperty::getHooks()
 * @see \Go\ParserReflection\ReflectionProperty::isDynamic()
 */
class PropertyHooksApiTest extends TestCase
{
    private const STUB_FILE = __DIR__ . '/Stub/FileWithPropertyHooks84.php';

    private const STUB_NAMESPACE = 'Go\\ParserReflection\\Stub\\';

    private const HOOKED_CLASS = self::STUB_NAMESPACE . 'ClassWithHookedAndPlainProperties';

    private const HOOKED_INTERFACE = self::STUB_NAMESPACE . 'InterfaceWithAbstractHook';

    /**
     * Expected hook names for every property of the stub class, keyed by the property name
     *
     * @var array<string, list<string>>
     */
    private const EXPECTED_HOOKS = [
        'plainProperty'        => [],
        'backedCounter'        => ['get', 'set'],
        'virtualWithBothHooks' => ['get', 'set'],
        'reversedHookOrder'    => ['get', 'set'],
        'virtualReadOnly'      => ['get'],
        'promotedProperty'     => [],
    ];

    protected function setUp(): void
    {
        // Stub file holds several classes at once, so it can not be resolved by the PSR-4 rules
        $locator = new CallableLocator(
            static fn(string $className): false|string
                => str_starts_with($className, self::STUB_NAMESPACE) ? self::STUB_FILE : false
        );
        ReflectionEngine::init($locator);
    }

    protected function tearDown(): void
    {
        ReflectionEngine::init(new ComposerLocator());
    }

    /**
     * This test intentionally goes first to check the pure AST-based path for the non-loaded classes
     */
    public function testGetHooksAndIsDynamicWithoutLoadingClass(): void
    {
        $this->assertFalse(
            class_exists(self::HOOKED_CLASS, false),
            'Stub class should not be loaded to verify the static analysis path'
        );

        $parsedClass = new ReflectionClass(self::HOOKED_CLASS);
        foreach (self::EXPECTED_HOOKS as $propertyName => $expectedHookNames) {
            $parsedProperty = $parsedClass->getProperty($propertyName);
            $hooks          = $parsedProperty->getHooks();

            $this->assertSame(
                $expectedHookNames,
                array_keys($hooks),
                "Hook names for property {$propertyName} should be resolved from the AST"
            );
            foreach ($hooks as $hookName => $hook) {
                $this->assertInstanceOf(ReflectionMethod::class, $hook);
                $this->assertSame('$' . $propertyName . '::' . $hookName, $hook->getName());
                $this->assertSame(self::HOOKED_CLASS, $hook->getDeclaringClass()->getName());
            }
            $this->assertFalse($parsedProperty->isDynamic(), "Property {$propertyName} should not be dynamic");
        }

        $this->assertFalse(class_exists(self::HOOKED_CLASS, false), 'Static analysis should not load the class');
    }

    public function testGetHooksForAbstractInterfaceHookWithoutLoadingInterface(): void
    {
        $this->assertFalse(
            interface_exists(self::HOOKED_INTERFACE, false),
            'Stub interface should not be loaded to verify the static analysis path'
        );

        $parsedProperty = (new ReflectionClass(self::HOOKED_INTERFACE))->getProperty('abstractHooked');
        $hooks          = $parsedProperty->getHooks();

        $this->assertSame(['get'], array_keys($hooks));
        $this->assertSame('$abstractHooked::get', $hooks['get']->getName());
        $this->assertTrue($parsedProperty->isAbstract());
        $this->assertFalse($parsedProperty->isDynamic());
    }

    public function testGetHooksParityWithNativeReflection(): void
    {
        $parsedClass = $this->loadStubClass();

        foreach (array_keys(self::EXPECTED_HOOKS) as $propertyName) {
            $parsedProperty = $parsedClass->getProperty($propertyName);
            $nativeProperty = new \ReflectionProperty(self::HOOKED_CLASS, $propertyName);

            $parsedHooks = $parsedProperty->getHooks();
            $nativeHooks = $nativeProperty->getHooks();

            $this->assertSame(
                array_keys($nativeHooks),
                array_keys($parsedHooks),
                "Hook names for property {$propertyName} should be equal to the native ones"
            );
            foreach ($nativeHooks as $hookName => $nativeHook) {
                $this->assertSame(
                    $nativeHook->getName(),
                    $parsedHooks[$hookName]->getName(),
                    "Hook method name for {$propertyName}::{$hookName} should be equal"
                );
                $this->assertSame(
                    $nativeHook->getNumberOfParameters(),
                    $parsedHooks[$hookName]->getNumberOfParameters(),
                    "Hook parameter count for {$propertyName}::{$hookName} should be equal"
                );
            }
            $this->assertSame(
                $nativeProperty->isDynamic(),
                $parsedProperty->isDynamic(),
                "Dynamic flag for property {$propertyName} should be equal to the native one"
            );
        }
    }

    public function testRawValueRoundTripOnRealInstance(): void
    {
        $parsedClass = $this->loadStubClass();
        $className   = self::HOOKED_CLASS;
        $instance    = new $className();

        $parsedProperty = $parsedClass->getProperty('backedCounter');
        $nativeProperty = new \ReflectionProperty($className, 'backedCounter');

        // Raw access bypasses the get hook that adds one to the stored value
        $this->assertSame(1, $parsedProperty->getRawValue($instance));
        $this->assertSame($nativeProperty->getRawValue($instance), $parsedProperty->getRawValue($instance));

        $parsedProperty->setRawValue($instance, 21);
        $this->assertSame(21, $parsedProperty->getRawValue($instance));
        // The set hook is bypassed too, but the get hook is still used for the normal read
        $this->assertSame(22, $instance->backedCounter);

        $this->assertFalse($parsedProperty->isLazy($instance));
    }

    public function testLazyObjectMethodsAreDelegatedToNativeReflection(): void
    {
        $parsedClass = $this->loadStubClass();
        $nativeClass = new \ReflectionClass(self::HOOKED_CLASS);
        $initializer = static function (object $instance): void {
            $instance->__construct('initialized');
        };

        $parsedProperty = $parsedClass->getProperty('plainProperty');
        $nativeProperty = new \ReflectionProperty(self::HOOKED_CLASS, 'plainProperty');

        $parsedGhost = $nativeClass->newLazyGhost($initializer);
        $nativeGhost = $nativeClass->newLazyGhost($initializer);

        $this->assertTrue($parsedProperty->isLazy($parsedGhost));
        $this->assertSame($nativeProperty->isLazy($nativeGhost), $parsedProperty->isLazy($parsedGhost));

        $parsedProperty->setRawValueWithoutLazyInitialization($parsedGhost, 'preset');
        $nativeProperty->setRawValueWithoutLazyInitialization($nativeGhost, 'preset');

        $this->assertSame('preset', $parsedProperty->getRawValue($parsedGhost));
        $this->assertSame($nativeProperty->getRawValue($nativeGhost), $parsedProperty->getRawValue($parsedGhost));
        $this->assertFalse($parsedProperty->isLazy($parsedGhost));

        $skippedGhost   = $nativeClass->newLazyGhost($initializer);
        $promotedParsed = $parsedClass->getProperty('promotedProperty');

        $this->assertTrue($promotedParsed->isLazy($skippedGhost));
        $promotedParsed->skipLazyInitialization($skippedGhost);
        $this->assertFalse($promotedParsed->isLazy($skippedGhost));
    }

    /**
     * Loads the stub file and returns a parsed reflection for the class with hooked properties
     */
    private function loadStubClass(): ReflectionClass
    {
        include_once self::STUB_FILE;

        return new ReflectionClass(self::HOOKED_CLASS);
    }
}
