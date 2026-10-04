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

use Go\ParserReflection\Locator\ComposerLocator;
use PhpParser\Error;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Parser;
use PhpParser\PhpVersion;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

class ReflectionEngineTest extends TestCase
{
    protected function tearDown(): void
    {
        // Restores the default locator and grammar for the following tests
        ReflectionEngine::init(new ComposerLocator());
    }

    public function testInitDoesNotCreateTheParser(): void
    {
        ReflectionEngine::getParser();

        ReflectionEngine::init(new ComposerLocator());

        $this->assertNull(new ReflectionProperty(ReflectionEngine::class, 'parser')->getValue());
        $this->assertNull(new ReflectionProperty(ReflectionEngine::class, 'traverser')->getValue());
    }

    public function testParserIsCreatedOnceOnFirstUse(): void
    {
        ReflectionEngine::init(new ComposerLocator());

        $parser = ReflectionEngine::getParser();

        $this->assertInstanceOf(Parser::class, $parser);
        $this->assertSame($parser, ReflectionEngine::getParser());
    }

    public function testParserUsesTheGrammarVersionGivenToInit(): void
    {
        ReflectionEngine::init(new ComposerLocator(), PhpVersion::fromComponents(8, 4));

        // The pipe operator exists since PHP 8.5, the 8.4 grammar rejects it
        $this->expectException(Error::class);
        ReflectionEngine::getParser()->parse('<?php $length = "value" |> strlen(...);');
    }

    public function testParseFileCreatesTheParserAndResolvesNames(): void
    {
        ReflectionEngine::init(new ComposerLocator());

        // A file name that does not exist, so the parsed tree cached for it serves no other test
        $fileName = __DIR__ . '/virtual/ReflectionEngineTestFile.php';
        $nodes    = ReflectionEngine::parseFile($fileName, '<?php namespace Foo; class Bar extends Baz {}');

        $this->assertNotEmpty($nodes);
        $class = ReflectionEngine::parseFileNamespace($fileName, 'Foo')->stmts[0] ?? null;
        $this->assertInstanceOf(Class_::class, $class);
        $this->assertSame('Foo\Baz', $class->extends?->getAttribute('resolvedName')?->toString());
    }
}
