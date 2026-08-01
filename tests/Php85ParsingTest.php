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
 * Verifies that sources written for a PHP version newer than the host one can still be analyzed.
 *
 * The stub file uses the PHP 8.5 pipe operator, therefore it must never be included or required,
 * it is only allowed to be parsed by the reflection engine.
 */
class Php85ParsingTest extends TestCase
{
    public const STUB_FILE = '/Stub/FileWithPhp85Syntax.php';

    public const STUB_CLASS = 'Go\ParserReflection\Stub\ClassWithPhp85PipeOperator';

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

    public function testFileWithNewerSyntaxIsParsed(): void
    {
        $reflectionFile = new ReflectionFile($this->stubFileName);

        $this->assertTrue($reflectionFile->isStrictMode());
        $this->assertTrue($reflectionFile->hasFileNamespace('Go\ParserReflection\Stub'));
    }

    public function testClassWithNewerSyntaxIsFound(): void
    {
        $reflectionFile      = new ReflectionFile($this->stubFileName);
        $reflectionNamespace = $reflectionFile->getFileNamespace('Go\ParserReflection\Stub');

        $this->assertArrayHasKey(self::STUB_CLASS, $reflectionNamespace->getClasses());

        $parsedClass = $reflectionNamespace->getClass(self::STUB_CLASS);
        $this->assertSame(self::STUB_CLASS, $parsedClass->getName());
        $this->assertSame($this->stubFileName, $parsedClass->getFileName());
        $this->assertSame(
            ['normalize', 'splitAndTrim', 'firstOrNull'],
            array_map(
                static fn(\ReflectionMethod $method): string => $method->getName(),
                $parsedClass->getMethods()
            )
        );
    }

    public function testMethodMetadataIsAvailable(): void
    {
        $reflectionFile      = new ReflectionFile($this->stubFileName);
        $reflectionNamespace = $reflectionFile->getFileNamespace('Go\ParserReflection\Stub');
        $parsedClass         = $reflectionNamespace->getClass(self::STUB_CLASS);

        $parsedMethod = $parsedClass->getMethod('splitAndTrim');
        $this->assertSame('splitAndTrim', $parsedMethod->getName());
        $this->assertFalse($parsedMethod->isStatic());
        $this->assertTrue($parsedMethod->isPublic());
        $this->assertSame('array', (string) $parsedMethod->getReturnType());

        $parsedParameters = $parsedMethod->getParameters();
        $this->assertCount(2, $parsedParameters);

        [$sentenceParameter, $separatorParameter] = $parsedParameters;
        $this->assertSame('sentence', $sentenceParameter->getName());
        $this->assertSame('string', (string) $sentenceParameter->getType());
        $this->assertFalse($sentenceParameter->isDefaultValueAvailable());

        $this->assertSame('separator', $separatorParameter->getName());
        $this->assertSame('string', (string) $separatorParameter->getType());
        $this->assertTrue($separatorParameter->isDefaultValueAvailable());
        $this->assertSame(',', $separatorParameter->getDefaultValue());

        $staticMethod = $parsedClass->getMethod('firstOrNull');
        $this->assertTrue($staticMethod->isStatic());
        $this->assertSame('?string', (string) $staticMethod->getReturnType());
    }

    public function testClassWithNewerSyntaxIsReflectableByName(): void
    {
        $stubFileName = $this->stubFileName;
        $locator      = new CallableLocator(
            static fn(string $className): false|string
                => $className === self::STUB_CLASS ? $stubFileName : false
        );
        ReflectionEngine::init($locator);

        $parsedClass = new ReflectionClass(self::STUB_CLASS);

        $this->assertSame(self::STUB_CLASS, $parsedClass->getName());
        $this->assertFalse(class_exists(self::STUB_CLASS, false), 'Stub class should not be loaded');
        $this->assertTrue($parsedClass->hasMethod('normalize'));
        $this->assertSame('string', (string) $parsedClass->getMethod('normalize')->getReturnType());
    }
}
