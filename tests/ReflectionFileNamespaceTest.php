<?php
namespace Go\ParserReflection;

use Go\ParserReflection\Stub\TestNamespaceClassFoo;

class ReflectionFileNamespaceTest extends \PHPUnit_Framework_TestCase
{
    const STUB_FILE = '/Stub/FileWithNamespaces.php';

    /**
     * @var ReflectionFileNamespace
     */
    protected $parsedRefFileNamespace;

    protected function setUp()
    {
        $fileName = stream_resolve_include_path(__DIR__ . self::STUB_FILE);
        $fileNode = ReflectionEngine::parseFile($fileName);

        $reflectionFile = new ReflectionFile($fileName, $fileNode);

        $parsedFileNamespace          = $reflectionFile->getFileNamespace('Go\ParserReflection\Stub');
        $this->parsedRefFileNamespace = $parsedFileNamespace;

        include_once $fileName;
    }

    public function testGetClass()
    {
        $refClass = $this->parsedRefFileNamespace->getClass('Unknown');
        $this->assertFalse($refClass);

        $refClass = $this->parsedRefFileNamespace->getClass(TestNamespaceClassFoo::class);
        $this->assertInstanceOf(\ReflectionClass::class, $refClass);
        $this->assertEquals(TestNamespaceClassFoo::class, $refClass->name);
    }

    public function testGetClasses()
    {
        $refClasses = $this->parsedRefFileNamespace->getClasses();
        $this->assertCount(2, $refClasses);
    }

    public function testGetConstant()
    {
        $constValue = $this->parsedRefFileNamespace->getConstant('Unknown');
        $this->assertFalse($constValue);

        $constValue = $this->parsedRefFileNamespace->getConstant('NAMESPACE_NAME');
        $this->assertNotFalse($constValue);
        $this->assertEquals(\Go\ParserReflection\Stub\NAMESPACE_NAME, $constValue);
    }

    public function testGetDocComment()
    {
        $docComment = $this->parsedRefFileNamespace->getDocComment();
        $this->assertNotEmpty($docComment);
    }

    public function testGetEndLine()
    {
        $endLine = $this->parsedRefFileNamespace->getEndLine();
        $this->assertEquals(\Go\ParserReflection\Stub\END_MARKER + 1, $endLine);
    }

    public function testGetFileName()
    {
        $fileName = $this->parsedRefFileNamespace->getFileName();
        $this->assertEquals(\Go\ParserReflection\Stub\FILE_NAME, $fileName);
    }

    public function testGetFunction()
    {
        $refFunction = $this->parsedRefFileNamespace->getFunction('Unknown');
        $this->assertFalse($refFunction);

        $refFunction = $this->parsedRefFileNamespace->getFunction('testFunctionBar');
        $this->assertInstanceOf(\ReflectionFunction::class, $refFunction);
        $this->assertEquals('testFunctionBar', $refFunction->name);
    }

    public function testGetFunctions()
    {
        $refFunctions = $this->parsedRefFileNamespace->getFunctions();
        $this->assertCount(1, $refFunctions);
    }

    public function testGetName()
    {
        $namespaceName = $this->parsedRefFileNamespace->getName();
        $this->assertEquals(\Go\ParserReflection\Stub\NAMESPACE_NAME, $namespaceName);
    }

    public function testGetNamespaceAliases()
    {
        $expectedAliases = [
            'ReflectionClass'     => 'UnusedReflectionClass',
            'PhpParser\Node'      => 'UnusedNode',
            'PhpParser\Node\Expr' => 'UnusedNodeExpr'
        ];

        $realAliases = $this->parsedRefFileNamespace->getNamespaceAliases();
        $this->assertEquals($expectedAliases, $realAliases);
    }

    public function testGetStartLine()
    {
        $startLine = $this->parsedRefFileNamespace->getStartLine();
        $this->assertEquals(\Go\ParserReflection\Stub\START_MARKER - 2, $startLine);
    }

    public function testHasClass()
    {
        $hasClass = $this->parsedRefFileNamespace->hasClass('Unknown');
        $this->assertFalse($hasClass);

        $hasClass = $this->parsedRefFileNamespace->hasClass(TestNamespaceClassFoo::class);
        $this->assertTrue($hasClass);
    }

    public function testHasConstant()
    {
        $hasConstant = $this->parsedRefFileNamespace->hasConstant('Unknown');
        $this->assertFalse($hasConstant);

        $hasConstant = $this->parsedRefFileNamespace->hasConstant('NAMESPACE_NAME');
        $this->assertTrue($hasConstant);
    }

    public function testHasFunction()
    {
        $hasFunction = $this->parsedRefFileNamespace->hasFunction('Unknown');
        $this->assertFalse($hasFunction);

        $hasFunction = $this->parsedRefFileNamespace->hasFunction('testFunctionBar');
        $this->assertTrue($hasFunction);
    }
}
