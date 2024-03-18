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
}
