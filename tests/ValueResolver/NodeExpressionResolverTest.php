<?php
namespace Go\ParserReflection\ValueResolver;

use PhpParser\Parser;
use PhpParser\ParserFactory;

class NodeExpressionResolverTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var null|Parser
     */
    protected $parser = null;

    protected function setUp()
    {
        $this->parser = (new ParserFactory)->create(ParserFactory::PREFER_PHP7);
    }

    /**
     * Testing passing PhpParser\Node\Expr as class for constant fetch
     *
     * We're already testing constant fetch with a explicit class name
     * elsewhere.
     */
    public function testResolveConstFetchFromExpressionAsClass()
    {
        $expressionNodeTree = $this->parser->parse("<?php ('\\\\Date' . 'Time')::ATOM;");
        $expressionSolver = new NodeExpressionResolver(NULL);
        $expressionSolver->process($expressionNodeTree[0]);
        $this->assertEquals(\DateTime::ATOM, $expressionSolver->getValue());
        $this->assertTrue($expressionSolver->isConstant());
        $this->assertEquals('DateTime::ATOM', $expressionSolver->getConstantName());
    }

    /**
     * Testing passing PhpParser\Node\Expr as class for constant fetch
     *
     * Evaluating a run-time value like a variable should throw an exception.
     *
     * @expectedException        Go\ParserReflection\ReflectionException
     * @expectedExceptionMessage Method Go\ParserReflection\ValueResolver\NodeExpressionResolver::resolveExprVariable() not found trying to resolve class constant
     */
    public function testResolveConstFetchFromVariableAsClass()
    {
        $expressionNodeTree = $this->parser->parse("<?php \$someVariable::FOO;");
        $expressionSolver = new NodeExpressionResolver(NULL);
        $expressionSolver->process($expressionNodeTree[0]);
    }

    /**
     * Testing passing non-expression as class for constant fetch
     *
     * Non-expressions should be invalid.
     *
     * @expectedException        Go\ParserReflection\ReflectionException
     * @expectedExceptionMessage Unable to resolve class constant
     */
    public function testResolveConstFetchFromNonExprAsClass()
    {
        $expressionNodeTree = $this->parser->parse("<?php ClassNameToReplace::Bar;");
        $notAnExpressionNodeTree = $this->parser->parse("<?php if (true) { \$baz = 3; }");
        // This should never happen...
        $expressionNodeTree[0]->expr->class = $notAnExpressionNodeTree[0];
        $expressionSolver = new NodeExpressionResolver(NULL);
        $expressionSolver->process($expressionNodeTree[0]);
    }
}
