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
use Go\ParserReflection\Resolver\NodeExpressionResolver;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\VariadicPlaceholder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Documents the current behavior of the engine for PHP 8.6 Partial Function Application (PFA).
 *
 * PHP 8.6 lets any call use the `?` placeholder for a single open argument, turning the call into
 * a Closure:
 *
 * ```php
 * $makeSlug = str_replace(' ', '-', ?);
 * ```
 *
 * The required nikic/php-parser (5.8.0, released 2026-06-04) has no grammar for the `?` argument
 * placeholder yet, so such sources simply can not be analyzed. This test pins down the *interim*
 * contract of issue #224: the engine must surface a clear, catchable parse error rather than
 * silently returning a truncated or corrupted AST.
 *
 * The related-but-already-supported first-class callable syntax `foo(...)` is asserted to keep
 * working, so that the follow-up work on PFA can be recognized as a real change of behavior.
 *
 * @see https://github.com/goaop/parser-reflection/issues/224
 */
class Php86PartialFunctionApplicationTest extends TestCase
{
    /**
     * Stub with PFA placeholders. It is not valid PHP 8.5 source, therefore it is never included
     * and it is deliberately kept out of AbstractTestCase::getFilesToAnalyze().
     */
    public const PFA_STUB_FILE = '/Stub/FileWithPartialFunctionApplication86.php';

    /**
     * Stub with first-class callables inside function-like bodies, which is parseable today.
     */
    public const FCC_STUB_FILE = '/Stub/FileWithFccInBodies.php';

    protected function tearDown(): void
    {
        // Some tests below replace the engine state, restore the default locator for the rest
        ReflectionEngine::init(new ComposerLocator());
    }

    /**
     * The PFA stub must never be part of the general parity data providers, as those parse
     * (and include) every listed file eagerly.
     */
    public function testPfaStubIsExcludedFromGeneralAnalysis(): void
    {
        $analyzedFiles = [];
        foreach (AbstractTestCase::getFilesToAnalyze() as $fileList) {
            foreach ($fileList as $fileName) {
                $analyzedFiles[] = basename($fileName);
            }
        }

        $this->assertNotContains(basename(self::PFA_STUB_FILE), $analyzedFiles);
    }

    /**
     * The stub really does contain PFA syntax, otherwise the assertions below would be vacuous.
     */
    public function testPfaStubContainsPlaceholderSyntax(): void
    {
        $stubContent = file_get_contents(__DIR__ . self::PFA_STUB_FILE);

        $this->assertIsString($stubContent);
        $this->assertStringContainsString("str_replace(' ', '-', ?)", $stubContent);
    }

    /**
     * Parsing a file with PFA placeholders fails loudly with a PhpParser\Error.
     *
     * Note that ReflectionEngine does not wrap parser errors, so PhpParser\Error is what actually
     * surfaces through ReflectionEngine::parseFile() and, transitively, through ReflectionFile.
     */
    public function testParsingStubWithPartialFunctionApplicationRaisesParseError(): void
    {
        $resolvedFileName = stream_resolve_include_path(__DIR__ . self::PFA_STUB_FILE);
        $this->assertIsString($resolvedFileName, 'PFA stub file should be available');

        $this->expectException(\PhpParser\Error::class);
        $this->expectExceptionMessageMatches('/Syntax error, unexpected \'\?\'/');

        ReflectionEngine::parseFile($resolvedFileName);
    }

    /**
     * The very same error must reach the user through the public ReflectionFile entry point,
     * i.e. it is not swallowed or converted into an empty list of namespaces.
     */
    public function testReflectionFileOnPartialFunctionApplicationRaisesParseError(): void
    {
        $resolvedFileName = stream_resolve_include_path(__DIR__ . self::PFA_STUB_FILE);
        $this->assertIsString($resolvedFileName, 'PFA stub file should be available');

        $this->expectException(\PhpParser\Error::class);
        $this->expectExceptionMessageMatches('/Syntax error, unexpected \'\?\'/');

        new ReflectionFile($resolvedFileName);
    }

    /**
     * A failed parse must not poison the engine cache: nothing is stored for that file name, so a
     * later attempt (e.g. after the php-parser constraint is bumped) re-parses from scratch.
     */
    public function testFailedParseIsNotCached(): void
    {
        $virtualFileName = __DIR__ . '/Stub/VirtualPfaFile.php';

        try {
            ReflectionEngine::parseFile($virtualFileName, '<?php $slug = str_replace(" ", "-", ?);');
            $this->fail('Parsing partial function application was expected to fail');
        } catch (\PhpParser\Error) {
            // expected
        }

        // The same virtual name now parses fine with valid content, which proves nothing was cached
        $nodes = ReflectionEngine::parseFile($virtualFileName, '<?php $slug = str_replace(" ", "-", $name);');
        $this->assertCount(1, $nodes);
    }

    /**
     * Every PFA placeholder position currently produces a syntax error mentioning the `?` token.
     *
     * @param string $source PHP source code using a partial function application
     */
    #[DataProvider('partialFunctionApplicationSourceProvider')]
    public function testEveryPlaceholderPositionRaisesParseError(string $source): void
    {
        $this->expectException(\PhpParser\Error::class);
        $this->expectExceptionMessageMatches('/Syntax error, unexpected \'\?\'/');

        ReflectionEngine::parseFile(__DIR__ . '/Stub/VirtualPfaSnippet.php', $source);
    }

    /**
     * @return \Generator<string, array{string}>
     */
    public static function partialFunctionApplicationSourceProvider(): \Generator
    {
        yield 'trailing placeholder'  => ['<?php $slug = str_replace(" ", "-", ?);'];
        yield 'leading placeholder'   => ['<?php $pad = str_pad(?, 10, ".");'];
        yield 'multiple placeholders' => ['<?php $fn = str_replace(?, ?, "text");'];
        yield 'placeholder in method' => ['<?php class A { public function m() { return $this->run(?); } }'];
        yield 'placeholder in static' => ['<?php class A { public function m() { return self::run(?, 1); } }'];
        yield 'placeholder in new'    => ['<?php $factory = new \DateTime(?);'];
    }

    /**
     * Guard against a regression of the already supported first-class callable syntax: reflecting
     * function-like bodies that contain `foo(...)` must keep working.
     */
    public function testFirstClassCallableInsideBodiesIsStillReflected(): void
    {
        $resolvedFileName = stream_resolve_include_path(__DIR__ . self::FCC_STUB_FILE);
        $this->assertIsString($resolvedFileName, 'FCC stub file should be available');

        $reflectionFile      = new ReflectionFile($resolvedFileName);
        $reflectionNamespace = $reflectionFile->getFileNamespace('Go\ParserReflection\Stub');

        $this->assertTrue($reflectionNamespace->hasFunction('functionWithFccInBody'));

        $parsedFunction = $reflectionNamespace->getFunction('functionWithFccInBody');
        $this->assertSame('Go\ParserReflection\Stub\functionWithFccInBody', $parsedFunction->getName());
        $this->assertSame(0, $parsedFunction->getNumberOfParameters());

        $parsedClass = $reflectionNamespace->getClass('Go\ParserReflection\Stub\ClassWithFccInBodies');
        $this->assertTrue($parsedClass->hasMethod('methodWithFccInBody'));

        $parsedMethod = $parsedClass->getMethod('methodWithFccInBody');
        $this->assertSame(2, $parsedMethod->getNumberOfParameters());
        $this->assertSame(1, $parsedMethod->getNumberOfRequiredParameters());
        $this->assertSame('separator', $parsedMethod->getParameters()[0]->getName());
        $this->assertSame(2, $parsedMethod->getParameters()[1]->getDefaultValue());

        foreach (['methodWithFccInClosureBody', 'methodWithStaticFccInBody', 'helper'] as $methodName) {
            $this->assertTrue($parsedClass->hasMethod($methodName));
        }
    }

    /**
     * The body of an FCC-containing method is still a well-formed AST that can be walked, which is
     * exactly what the future PFA support has to preserve.
     */
    public function testFirstClassCallableBodyKeepsVariadicPlaceholderNode(): void
    {
        $resolvedFileName = stream_resolve_include_path(__DIR__ . self::FCC_STUB_FILE);
        $this->assertIsString($resolvedFileName, 'FCC stub file should be available');

        $reflectionFile = new ReflectionFile($resolvedFileName);
        $parsedClass    = $reflectionFile
            ->getFileNamespace('Go\ParserReflection\Stub')
            ->getClass('Go\ParserReflection\Stub\ClassWithFccInBodies');

        $methodNode = $parsedClass->getMethod('methodWithFccInBody')->getNode();
        $statements = $methodNode->stmts ?? [];
        $this->assertCount(1, $statements);

        $returnStatement = $statements[0];
        $this->assertInstanceOf(Node\Stmt\Return_::class, $returnStatement);
        $this->assertInstanceOf(Expr\FuncCall::class, $returnStatement->expr);
        $this->assertTrue($returnStatement->expr->isFirstClassCallable());
        $this->assertInstanceOf(VariadicPlaceholder::class, $returnStatement->expr->args[0]);
    }

    /**
     * A placeholder argument that the resolver can not evaluate has to degrade into a regular
     * ReflectionException, never into a fatal error or a silently wrong value.
     *
     * The node built here is the closest available stand-in for a future PFA argument: a call that
     * is *not* a first-class callable but still carries a non-Arg placeholder argument.
     */
    public function testResolverFailsGracefullyOnPlaceholderArgument(): void
    {
        $funcCallNode = new Expr\FuncCall(
            new Node\Name\FullyQualified('str_replace'),
            [
                new Node\Arg(new Node\Scalar\String_(' ')),
                new Node\Arg(new Node\Scalar\String_('-')),
                new VariadicPlaceholder(),
            ]
        );

        $this->expectException(ReflectionException::class);
        $this->expectExceptionMessage('Cannot statically resolve a variadic placeholder argument in a function call');

        (new NodeExpressionResolver(null))->process($funcCallNode);
    }

    /**
     * The same graceful degradation is required for constructor calls, which PFA also covers.
     */
    public function testResolverFailsGracefullyOnPlaceholderArgumentInNewExpression(): void
    {
        $newNode = new Expr\New_(
            new Node\Name\FullyQualified('DateTimeImmutable'),
            [new VariadicPlaceholder()]
        );

        $this->expectException(ReflectionException::class);
        $this->expectExceptionMessage('Cannot statically resolve a variadic placeholder argument in a constructor call');

        (new NodeExpressionResolver(null))->process($newNode);
    }

    /**
     * Any node type the resolver has no handler for (which is what an eventual PFA placeholder node
     * would be, before explicit support is added) must produce a ReflectionException as well.
     */
    public function testResolverFailsGracefullyOnUnknownNodeType(): void
    {
        $this->expectException(ReflectionException::class);
        $this->expectExceptionMessageMatches('/Could not find handler for the .*NodeExpressionResolver::resolveExpr\w+ method/');

        (new NodeExpressionResolver(null))->process(new Expr\Variable('placeholder'));
    }
}
