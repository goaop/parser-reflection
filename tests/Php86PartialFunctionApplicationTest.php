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
use PhpParser\Node\ArgPlaceholder;
use PhpParser\Node\Expr;
use PhpParser\Node\VariadicPlaceholder;
use PhpParser\NodeFinder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Covers reflection of PHP 8.6 Partial Function Application (PFA).
 *
 * PHP 8.6 lets any call use the `?` placeholder for a single open argument, turning the call into
 * a Closure:
 *
 * ```php
 * $makeSlug = str_replace(' ', '-', ?);
 * ```
 *
 * Since nikic/php-parser 5.9.0 the `?` placeholder is parsed into a `PhpParser\Node\ArgPlaceholder`
 * node (the "all remaining arguments" form `foo(1, ...)` reuses `VariadicPlaceholder`), so sources
 * containing PFA now reflect cleanly on every supported host runtime — the engine parses with the
 * newest supported grammar regardless of the PHP version it runs on.
 *
 * A PFA expression in a constant-expression position still degrades into a ReflectionException,
 * the same contract user-defined first-class callables already have: a Closure cannot be
 * represented statically.
 *
 * @see https://github.com/goaop/parser-reflection/issues/224
 */
class Php86PartialFunctionApplicationTest extends TestCase
{
    /**
     * Stub with PFA placeholders. It parses on every runtime since php-parser 5.9, but it can only
     * be *included* by a PHP 8.6+ runtime, therefore it is kept out of the general parity data
     * providers, which include every listed file eagerly.
     */
    public const PFA_STUB_FILE = '/Stub/FileWithPartialFunctionApplication86.php';

    /**
     * Stub with first-class callables inside function-like bodies.
     */
    public const FCC_STUB_FILE = '/Stub/FileWithFccInBodies.php';

    public const STUB_NAMESPACE = 'Go\ParserReflection\Stub';

    protected function tearDown(): void
    {
        // Some tests below replace the engine state, restore the default locator for the rest
        ReflectionEngine::init(new ComposerLocator());
    }

    /**
     * The PFA stub must not be part of the general parity data providers: those include every
     * listed file eagerly, and PFA syntax is a compile error on a PHP 8.5 runtime.
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
     * A file with PFA placeholders parses cleanly through the engine, even on a PHP 8.5 host:
     * ReflectionEngine relies on the newest grammar supported by php-parser, not on the host
     * runtime, so the 5.9 grammar is picked up without any engine change.
     */
    public function testStubWithPartialFunctionApplicationIsParsed(): void
    {
        $resolvedFileName = stream_resolve_include_path(__DIR__ . self::PFA_STUB_FILE);
        $this->assertIsString($resolvedFileName, 'PFA stub file should be available');

        $fileNodes = ReflectionEngine::parseFile($resolvedFileName);

        $this->assertNotEmpty($fileNodes);
        $placeholders = (new NodeFinder())->findInstanceOf($fileNodes, ArgPlaceholder::class);
        $this->assertNotEmpty($placeholders, 'The parsed AST should contain ArgPlaceholder nodes');
    }

    /**
     * The public ReflectionFile entry point reflects a PFA-containing source without errors:
     * namespaces, functions, classes and their signatures are all available.
     */
    public function testReflectionFileReflectsPartialFunctionApplicationStub(): void
    {
        $resolvedFileName = stream_resolve_include_path(__DIR__ . self::PFA_STUB_FILE);
        $this->assertIsString($resolvedFileName, 'PFA stub file should be available');

        $reflectionFile      = new ReflectionFile($resolvedFileName);
        $reflectionNamespace = $reflectionFile->getFileNamespace(self::STUB_NAMESPACE);

        $this->assertTrue($reflectionNamespace->hasFunction('functionWithPartialApplicationInBody'));
        $this->assertTrue($reflectionNamespace->hasFunction('functionWithTrailingVariadicPlaceholder'));

        $parsedFunction = $reflectionNamespace->getFunction('functionWithPartialApplicationInBody');
        $this->assertSame('Closure', (string) $parsedFunction->getReturnType());
        $this->assertSame(0, $parsedFunction->getNumberOfParameters());

        $parsedClass = $reflectionNamespace->getClass(self::STUB_NAMESPACE . '\ClassWithPartialFunctionApplication');
        foreach (['methodWithPartialApplication', 'closureWithPartialApplication', 'staticCallWithPartialApplication', 'helper'] as $methodName) {
            $this->assertTrue($parsedClass->hasMethod($methodName));
        }
        $this->assertSame(2, $parsedClass->getMethod('helper')->getNumberOfParameters());
    }

    /**
     * The body of a PFA-containing method is a well-formed AST: the call carries an ArgPlaceholder
     * argument and reports itself as a partial function application, distinct from a first-class
     * callable.
     */
    public function testMethodBodyKeepsArgPlaceholderNode(): void
    {
        $resolvedFileName = stream_resolve_include_path(__DIR__ . self::PFA_STUB_FILE);
        $this->assertIsString($resolvedFileName, 'PFA stub file should be available');

        $parsedClass = (new ReflectionFile($resolvedFileName))
            ->getFileNamespace(self::STUB_NAMESPACE)
            ->getClass(self::STUB_NAMESPACE . '\ClassWithPartialFunctionApplication');

        $methodNode = $parsedClass->getMethod('methodWithPartialApplication')->getNode();
        $statements = $methodNode->stmts ?? [];
        $this->assertCount(1, $statements);

        $returnStatement = $statements[0];
        $this->assertInstanceOf(Node\Stmt\Return_::class, $returnStatement);
        $this->assertInstanceOf(Expr\FuncCall::class, $returnStatement->expr);
        $this->assertTrue($returnStatement->expr->isPartialFunctionApplication());
        $this->assertFalse($returnStatement->expr->isFirstClassCallable());
        $this->assertInstanceOf(ArgPlaceholder::class, $returnStatement->expr->args[0]);
    }

    /**
     * Every PFA placeholder position parses into the expected number of ArgPlaceholder nodes.
     *
     * @param string $source PHP source code using a partial function application
     */
    #[DataProvider('partialFunctionApplicationSourceProvider')]
    public function testEveryPlaceholderPositionIsParsed(string $source, int $expectedPlaceholders): void
    {
        $fileNodes = ReflectionEngine::parseFile(__DIR__ . '/Stub/VirtualPfaSnippet.php', $source);

        $placeholders = (new NodeFinder())->findInstanceOf($fileNodes, ArgPlaceholder::class);
        $this->assertCount($expectedPlaceholders, $placeholders);
    }

    /**
     * @return \Generator<string, array{string, int}>
     */
    public static function partialFunctionApplicationSourceProvider(): \Generator
    {
        yield 'trailing placeholder'  => ['<?php $slug = str_replace(" ", "-", ?);', 1];
        yield 'leading placeholder'   => ['<?php $pad = str_pad(?, 10, ".");', 1];
        yield 'multiple placeholders' => ['<?php $fn = str_replace(?, ?, "text");', 2];
        yield 'named placeholder'     => ['<?php $fn = str_contains(haystack: "text", needle: ?);', 1];
        yield 'placeholder in method' => ['<?php class A { public function m() { return $this->run(?); } }', 1];
        yield 'placeholder in static' => ['<?php class A { public function m() { return self::run(?, 1); } }', 1];
        yield 'placeholder in new'    => ['<?php $factory = new \DateTime(?);', 1];
    }

    /**
     * A failed parse must not poison the engine cache: nothing is stored for that file name, so a
     * later attempt with corrected content re-parses from scratch. (PFA no longer fails to parse,
     * so a genuinely broken source pins this behavior now.)
     */
    public function testFailedParseIsNotCached(): void
    {
        $virtualFileName = __DIR__ . '/Stub/VirtualBrokenFile.php';

        try {
            ReflectionEngine::parseFile($virtualFileName, '<?php $slug = str_replace(;');
            $this->fail('Parsing a broken source was expected to fail');
        } catch (\PhpParser\Error) {
            // expected
        }

        // The same virtual name now parses fine with valid content, which proves nothing was cached
        $nodes = ReflectionEngine::parseFile($virtualFileName, '<?php $slug = str_replace(" ", "-", $name);');
        $this->assertCount(1, $nodes);
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
        $reflectionNamespace = $reflectionFile->getFileNamespace(self::STUB_NAMESPACE);

        $this->assertTrue($reflectionNamespace->hasFunction('functionWithFccInBody'));

        $parsedFunction = $reflectionNamespace->getFunction('functionWithFccInBody');
        $this->assertSame(self::STUB_NAMESPACE . '\functionWithFccInBody', $parsedFunction->getName());
        $this->assertSame(0, $parsedFunction->getNumberOfParameters());

        $parsedClass = $reflectionNamespace->getClass(self::STUB_NAMESPACE . '\ClassWithFccInBodies');
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
     * The body of an FCC-containing method still parses into a first-class callable with its
     * established VariadicPlaceholder representation. Note that php-parser 5.9 deliberately
     * reports a first-class callable as a special case of partial function application, so
     * isPartialFunctionApplication() is true for it as well.
     */
    public function testFirstClassCallableBodyKeepsVariadicPlaceholderNode(): void
    {
        $resolvedFileName = stream_resolve_include_path(__DIR__ . self::FCC_STUB_FILE);
        $this->assertIsString($resolvedFileName, 'FCC stub file should be available');

        $reflectionFile = new ReflectionFile($resolvedFileName);
        $parsedClass    = $reflectionFile
            ->getFileNamespace(self::STUB_NAMESPACE)
            ->getClass(self::STUB_NAMESPACE . '\ClassWithFccInBodies');

        $methodNode = $parsedClass->getMethod('methodWithFccInBody')->getNode();
        $statements = $methodNode->stmts ?? [];
        $this->assertCount(1, $statements);

        $returnStatement = $statements[0];
        $this->assertInstanceOf(Node\Stmt\Return_::class, $returnStatement);
        $this->assertInstanceOf(Expr\FuncCall::class, $returnStatement->expr);
        $this->assertTrue($returnStatement->expr->isFirstClassCallable());
        // A first-class callable counts as a partial function application in php-parser 5.9+
        $this->assertTrue($returnStatement->expr->isPartialFunctionApplication());
        $this->assertInstanceOf(VariadicPlaceholder::class, $returnStatement->expr->args[0]);
    }

    /**
     * A PFA placeholder argument in a constant-expression position degrades into a regular
     * ReflectionException, never into a fatal error or a silently wrong value — the same contract
     * user-defined first-class callables already have.
     */
    public function testResolverFailsGracefullyOnArgPlaceholderInFunctionCall(): void
    {
        $funcCallNode = new Expr\FuncCall(
            new Node\Name\FullyQualified('str_replace'),
            [
                new Node\Arg(new Node\Scalar\String_(' ')),
                new Node\Arg(new Node\Scalar\String_('-')),
                new ArgPlaceholder(),
            ]
        );

        $this->expectException(ReflectionException::class);
        $this->expectExceptionMessage('Cannot statically resolve a placeholder argument in a function call');

        (new NodeExpressionResolver(null))->process($funcCallNode);
    }

    /**
     * The same graceful degradation is required for constructor calls, which PFA also covers.
     */
    public function testResolverFailsGracefullyOnArgPlaceholderInNewExpression(): void
    {
        $newNode = new Expr\New_(
            new Node\Name\FullyQualified('DateTimeImmutable'),
            [new ArgPlaceholder()]
        );

        $this->expectException(ReflectionException::class);
        $this->expectExceptionMessage('Cannot statically resolve a placeholder argument in a constructor call');

        (new NodeExpressionResolver(null))->process($newNode);
    }

    /**
     * The "all remaining arguments" placeholder keeps the same resolver contract.
     */
    public function testResolverFailsGracefullyOnVariadicPlaceholderArgument(): void
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
        $this->expectExceptionMessage('Cannot statically resolve a placeholder argument in a function call');

        (new NodeExpressionResolver(null))->process($funcCallNode);
    }

    /**
     * Any node type the resolver has no handler for must produce a ReflectionException as well.
     */
    public function testResolverFailsGracefullyOnUnknownNodeType(): void
    {
        $this->expectException(ReflectionException::class);
        $this->expectExceptionMessageMatches('/Could not find handler for the .*NodeExpressionResolver::resolveExpr\w+ method/');

        (new NodeExpressionResolver(null))->process(new Expr\Variable('placeholder'));
    }

    /**
     * On a PHP 8.6 runtime the stub is genuinely loadable and behaves as reflected: the parsed
     * signatures match native reflection, and the partial applications evaluate to Closures.
     */
    public function testNativeBehaviorOnPhp86(): void
    {
        if (PHP_VERSION_ID < 80600) {
            $this->markTestSkipped('Executing partial function application requires a PHP 8.6 runtime');
        }

        $resolvedFileName = stream_resolve_include_path(__DIR__ . self::PFA_STUB_FILE);
        $this->assertIsString($resolvedFileName, 'PFA stub file should be available');

        include_once $resolvedFileName;

        $functionName   = self::STUB_NAMESPACE . '\functionWithPartialApplicationInBody';
        $nativeFunction = new \ReflectionFunction($functionName);
        $parsedFunction = (new ReflectionFile($resolvedFileName))
            ->getFileNamespace(self::STUB_NAMESPACE)
            ->getFunction('functionWithPartialApplicationInBody');

        $this->assertSame((string) $nativeFunction->getReturnType(), (string) $parsedFunction->getReturnType());
        $this->assertSame($nativeFunction->getNumberOfParameters(), $parsedFunction->getNumberOfParameters());

        $partialApplication = $functionName();
        $this->assertInstanceOf(\Closure::class, $partialApplication);
        $this->assertSame('a-b', $partialApplication('a b'));
    }
}
