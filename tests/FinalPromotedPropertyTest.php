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
 * Verifies that the PHP 8.5 `final` modifier on promoted constructor properties is reflected.
 *
 * The stub file uses PHP 8.5 syntax, therefore it must never be included or required,
 * it is only allowed to be parsed by the reflection engine.
 */
class FinalPromotedPropertyTest extends TestCase
{
    public const STUB_FILE = '/Stub/FileWithFinalPromoted85.php';

    public const STUB_CLASS = 'Go\ParserReflection\Stub\ClassWithFinalPromotedProperty85';

    private string $stubFileName;

    protected function setUp(): void
    {
        $resolvedFileName = stream_resolve_include_path(__DIR__ . self::STUB_FILE);
        $this->assertIsString($resolvedFileName, 'PHP 8.5 stub file should be available');

        $this->stubFileName = $resolvedFileName;
    }

    protected function tearDown(): void
    {
        // Restores the default locator for the following tests, because some of them replace it
        ReflectionEngine::init(new ComposerLocator());
    }

    public function testFinalPromotedPropertyIsFinal(): void
    {
        $parsedClass = $this->getParsedClass();

        $parsedProperty = $parsedClass->getProperty('finalPromoted');
        $this->assertTrue($parsedProperty->isPromoted(), 'Property should be promoted');
        $this->assertTrue($parsedProperty->isFinal(), 'Promoted property should be final');
        $this->assertSame(
            \ReflectionProperty::IS_FINAL,
            $parsedProperty->getModifiers() & \ReflectionProperty::IS_FINAL,
            'Modifiers should contain the IS_FINAL bit'
        );
        $this->assertTrue($parsedProperty->isPublic());
    }

    public function testPlainPromotedPropertyIsNotFinal(): void
    {
        $parsedClass = $this->getParsedClass();

        $parsedProperty = $parsedClass->getProperty('plainPromoted');
        $this->assertTrue($parsedProperty->isPromoted(), 'Property should be promoted');
        $this->assertFalse($parsedProperty->isFinal(), 'Plain promoted property should not be final');
        $this->assertSame(
            0,
            $parsedProperty->getModifiers() & \ReflectionProperty::IS_FINAL,
            'Modifiers should not contain the IS_FINAL bit'
        );
    }

    public function testFinalReadonlyPromotedPropertyIsFinal(): void
    {
        $parsedClass = $this->getParsedClass();

        $parsedProperty = $parsedClass->getProperty('finalReadonlyPromoted');
        $this->assertTrue($parsedProperty->isFinal());
        $this->assertTrue($parsedProperty->isReadOnly());
        $this->assertTrue($parsedProperty->isProtected());
        $this->assertSame(
            \ReflectionProperty::IS_FINAL,
            $parsedProperty->getModifiers() & \ReflectionProperty::IS_FINAL
        );
    }

    public function testPrivateSetPromotedPropertyRemainsImplicitlyFinal(): void
    {
        $parsedClass = $this->getParsedClass();

        $parsedProperty = $parsedClass->getProperty('privateSetPromoted');
        $this->assertTrue($parsedProperty->isPrivateSet(), 'Property should have private(set) visibility');
        $this->assertTrue($parsedProperty->isFinal(), 'Property with private(set) is implicitly final');
        $this->assertSame(
            \ReflectionProperty::IS_FINAL,
            $parsedProperty->getModifiers() & \ReflectionProperty::IS_FINAL
        );
    }

    public function testStubClassIsNeverLoaded(): void
    {
        $parsedClass = $this->getParsedClass();

        $this->assertSame(self::STUB_CLASS, $parsedClass->getName());
        $this->assertFalse(class_exists(self::STUB_CLASS, false), 'Stub class should not be loaded');
    }

    /**
     * Reflects the stub class without triggering autoloading of the PHP 8.5 source
     */
    private function getParsedClass(): ReflectionClass
    {
        $stubFileName = $this->stubFileName;
        $locator      = new CallableLocator(
            static fn(string $className): false|string
                => $className === self::STUB_CLASS ? $stubFileName : false
        );
        ReflectionEngine::init($locator);

        return new ReflectionClass(self::STUB_CLASS);
    }
}
