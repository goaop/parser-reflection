<?php
declare(strict_types=1);

namespace Go\ParserReflection\Resolver;


use PHPUnit\Framework\TestCase;
use PhpParser\Parser;
use PhpParser\ParserFactory;

class NodeExpressionResolverTest extends TestCase
{
    protected Parser $parser;

    protected function setUp(): void
    {
        $this->parser = (new ParserFactory)->createForHostVersion();
    }

    /**
     * Testing passing PhpParser\Node\Expr as class for constant fetch
     *
     * We're already testing constant fetch with a explicit class name
     * elsewhere.
     */
    public function testResolveConstFetchFromExpressionAsClass(): void
    {
        $expressionNodeTree = $this->parser->parse("<?php ('\\\\Date' . 'Time')::ATOM;");
        $expressionSolver = new NodeExpressionResolver(NULL);
        $expressionSolver->process($expressionNodeTree[0]);
        $this->assertSame(\DateTime::ATOM, $expressionSolver->getValue());
        $this->assertTrue($expressionSolver->isConstant());
        $this->assertSame('DateTime::ATOM', $expressionSolver->getConstantName());
    }

    /**
     * Testing passing PhpParser\Node\Expr as class for constant fetch
     *
     * Evaluating a run-time value like a variable should throw an exception.
     */
    public function testResolveConstFetchFromVariableAsClass(): void
    {
        $this->expectException(\Go\ParserReflection\ReflectionException::class);
        $this->expectExceptionMessage('Could not find handler for the Go\ParserReflection\Resolver\NodeExpressionResolver::resolveExprVariable method');

        $expressionNodeTree = $this->parser->parse("<?php \$someVariable::FOO;");
        $expressionSolver = new NodeExpressionResolver(NULL);
        $expressionSolver->process($expressionNodeTree[0]);
    }

    /**
     * Testing passing non-expression as class for constant fetch
     *
     * Non-expressions should be invalid.
     */
    public function testResolveConstFetchFromNonExprAsClass(): void
    {
        $this->expectException(\Go\ParserReflection\ReflectionException::class);
        $this->expectExceptionMessage('Could not find handler for the Go\ParserReflection\Resolver\NodeExpressionResolver::resolveStmtIf method');

        $expressionNodeTree = $this->parser->parse("<?php ClassNameToReplace::Bar;");
        $notAnExpressionNodeTree = $this->parser->parse("<?php if (true) { \$baz = 3; }");
        // This should never happen...
        $expressionNodeTree[0]->expr->class = $notAnExpressionNodeTree[0];
        $expressionSolver = new NodeExpressionResolver(NULL);
        $expressionSolver->process($expressionNodeTree[0]);
    }

    /**
     * Testing resolving new expression with a simple instantiation
     */
    public function testResolveNewExpression(): void
    {
        $expressionNodeTree = $this->parser->parse("<?php new \\DateTime('2023-01-01');");
        $expressionSolver = new NodeExpressionResolver(NULL);
        $expressionSolver->process($expressionNodeTree[0]);
        
        $value = $expressionSolver->getValue();
        $this->assertInstanceOf(\DateTime::class, $value);
        $this->assertSame('2023-01-01', $value->format('Y-m-d'));
    }

    /**
     * Testing resolving new expression without constructor arguments
     */
    public function testResolveNewExpressionWithoutArguments(): void
    {
        $expressionNodeTree = $this->parser->parse("<?php new \\stdClass();");
        $expressionSolver = new NodeExpressionResolver(NULL);
        $expressionSolver->process($expressionNodeTree[0]);
        
        $value = $expressionSolver->getValue();
        $this->assertInstanceOf(\stdClass::class, $value);
    }

    /**
     * Testing resolving new expression with DateTimeImmutable as in the issue
     */
    public function testResolveNewExpressionDateTimeImmutable(): void
    {
        $expressionNodeTree = $this->parser->parse("<?php new \\DateTimeImmutable('today');");
        $expressionSolver = new NodeExpressionResolver(NULL);
        $expressionSolver->process($expressionNodeTree[0]);
        
        $value = $expressionSolver->getValue();
        $this->assertInstanceOf(\DateTimeImmutable::class, $value);
    }

    /**
     * Testing resolving property fetch on a backed enum case (e.g. Enum::CASE->value)
     */
    public function testResolvePropertyFetchOnEnumCase(): void
    {
        require_once __DIR__ . '/../Stub/FileWithClasses81.php';

        $expressionNodeTree = $this->parser->parse("<?php \\Go\\ParserReflection\\Stub\\BackedPhp81EnumHTTPMethods::GET->value;");
        $expressionSolver = new NodeExpressionResolver(NULL);
        $expressionSolver->process($expressionNodeTree[0]);
        $this->assertSame('get', $expressionSolver->getValue());
    }

    /**
     * Testing resolving first-class callable syntax for built-in function
     */
    public function testResolveFirstClassCallableFunctionBuiltin(): void
    {
        $expressionNodeTree = $this->parser->parse("<?php strlen(...);");
        $expressionSolver = new NodeExpressionResolver(null);
        $expressionSolver->process($expressionNodeTree[0]);

        $value = $expressionSolver->getValue();
        $this->assertInstanceOf(\Closure::class, $value);
        $this->assertSame(6, $value('foobar'));
    }

    /**
     * Testing resolving first-class callable syntax for another built-in function
     */
    public function testResolveFirstClassCallableFunctionBuiltinArrayMap(): void
    {
        $expressionNodeTree = $this->parser->parse("<?php array_reverse(...);");
        $expressionSolver = new NodeExpressionResolver(null);
        $expressionSolver->process($expressionNodeTree[0]);

        $value = $expressionSolver->getValue();
        $this->assertInstanceOf(\Closure::class, $value);
        $this->assertSame([3, 2, 1], $value([1, 2, 3]));
    }

    /**
     * Testing that first-class callable syntax for user-defined function throws ReflectionException
     */
    public function testResolveFirstClassCallableFunctionUserDefinedThrows(): void
    {
        $this->expectException(\Go\ParserReflection\ReflectionException::class);
        $this->expectExceptionMessageMatches('/user-defined function.*cannot be resolved/');

        // Define a user function to test with
        if (!function_exists('Go\ParserReflection\Resolver\testUserDefinedFunction')) {
            eval('namespace Go\\ParserReflection\\Resolver; function testUserDefinedFunction() {}');
        }

        $expressionNodeTree = $this->parser->parse("<?php Go\\ParserReflection\\Resolver\\testUserDefinedFunction(...);");
        $expressionSolver = new NodeExpressionResolver(null);
        $expressionSolver->process($expressionNodeTree[0]);
    }

    /**
     * Testing resolving first-class callable syntax for a static method of a built-in class
     */
    public function testResolveFirstClassCallableStaticMethodBuiltin(): void
    {
        $expressionNodeTree = $this->parser->parse("<?php \\DateTime::createFromFormat(...);");
        $expressionSolver = new NodeExpressionResolver(null);
        $expressionSolver->process($expressionNodeTree[0]);

        $value = $expressionSolver->getValue();
        $this->assertInstanceOf(\Closure::class, $value);
        $result = $value('Y-m-d', '2023-01-01');
        $this->assertInstanceOf(\DateTime::class, $result);
        $this->assertSame('2023-01-01', $result->format('Y-m-d'));
    }

    /**
     * Testing that first-class callable syntax for user-defined static method throws ReflectionException
     */
    public function testResolveFirstClassCallableStaticMethodUserDefinedThrows(): void
    {
        $this->expectException(\Go\ParserReflection\ReflectionException::class);
        $this->expectExceptionMessageMatches('/user-defined method.*cannot be resolved/');

        $expressionNodeTree = $this->parser->parse("<?php \\Go\\ParserReflection\\ReflectionEngine::locateClassFile(...);");
        $expressionSolver = new NodeExpressionResolver(null);
        $expressionSolver->process($expressionNodeTree[0]);
    }

    /**
     * Testing that first-class callable syntax for instance methods throws ReflectionException
     */
    public function testResolveFirstClassCallableInstanceMethodThrows(): void
    {
        $this->expectException(\Go\ParserReflection\ReflectionException::class);
        $this->expectExceptionMessageMatches('/instance methods.*cannot be resolved statically/');

        $expressionNodeTree = $this->parser->parse("<?php \$obj->method(...);");
        $expressionSolver = new NodeExpressionResolver(null);
        $expressionSolver->process($expressionNodeTree[0]);
    }
}
