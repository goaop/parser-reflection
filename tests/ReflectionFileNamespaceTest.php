<?php
declare(strict_types=1);

namespace Go\ParserReflection;

use PHPUnit\Framework\TestCase;
use Go\ParserReflection\Stub\TestNamespaceClassFoo;
use const Go\ParserReflection\Stub\END_MARKER;
use const Go\ParserReflection\Stub\FILE_NAME;
use const Go\ParserReflection\Stub\NAMESPACE_NAME;
use const Go\ParserReflection\Stub\START_MARKER;

class ReflectionFileNamespaceTest extends TestCase
{
    const STUB_FILE = '\\Stub\\FileWithNamespaces.php';
    const STUB_GLOBAL_FILE = '/Stub/FileWithGlobalNamespace.php';

    /**
     * @var ReflectionFileNamespace
     */
    protected $parsedRefFileNamespace;

    protected function setUp(): void
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
        $this->assertEquals(NAMESPACE_NAME, $constValue);
    }

    public function testGetConstants()
    {
        $constValue = $this->parsedRefFileNamespace->getConstants(true);
        $this->assertEquals(
            array(
                'START_MARKER' => 9,
                'NAMESPACE_NAME' => 'Go\ParserReflection\Stub',
                'FILE_NAME' => __DIR__ . self::STUB_FILE,
                'END_MARKER' => 26,
                'INT_CONST' => 5,
            ),
            $constValue
        );

        $constValue = $this->parsedRefFileNamespace->getConstants();
        $this->assertNotFalse($constValue);
        $this->assertEquals(
            array(
                'START_MARKER' => 9,
                'NAMESPACE_NAME' => 'Go\ParserReflection\Stub',
                'FILE_NAME' => __DIR__ . self::STUB_FILE,
                'END_MARKER' => 26,
            ),
            $constValue
        );
    }

    public function testGetConstantsCacheIndependence()
    {
        $globalConstants = $this->parsedRefFileNamespace->getConstants(true);
        $this->assertArrayHasKey('FILE_NAME', $globalConstants, 'Namespaced constant found.');
        $this->assertArrayHasKey('INT_CONST', $globalConstants, 'Global constant found.');

        $constants = $this->parsedRefFileNamespace->getConstants();
        $this->assertArrayHasKey('FILE_NAME', $constants, 'Namespaced constant found.');
        $this->assertArrayNotHasKey('INT_CONST', $constants, 'Global constant not found.');

        $this->assertNotEmpty($this->parsedRefFileNamespace->getConstant('FILE_NAME'), 'Namespaced constant found.');
        $this->assertTrue($this->parsedRefFileNamespace->hasConstant('FILE_NAME'), 'Namespaced constant found.');

        $this->assertFalse($this->parsedRefFileNamespace->hasConstant('INT_CONST'), 'Global constant not found.');
        $this->assertFalse($this->parsedRefFileNamespace->getConstant('INT_CONST'), 'Global constant not found.');
    }

    public function testGetGlobalConstants()
    {
        $fileName = stream_resolve_include_path(__DIR__ . self::STUB_GLOBAL_FILE);
        $reflectionFile = new ReflectionFile($fileName);

        $reflectionFileNamespace = $reflectionFile->getFileNamespace('');

        $this->assertSame(
            array(
                // Scalar types are handled.
                'INT_CONST' => 5,
                'STRING_CONST' => 'text',
                'BOOLEAN_CONST' => true,

                // Expressions are handled partially.
                'EXPRESSION_CONST' => false,
                'FUNCTION_CONST' => null,
            ),
            $reflectionFileNamespace->getConstants(true)
        );
    }

    public function testGetDocComment()
    {
        $docComment = $this->parsedRefFileNamespace->getDocComment();
        $this->assertNotEmpty($docComment);
    }

    public function testGetEndLine()
    {
        $endLine = $this->parsedRefFileNamespace->getEndLine();
        $this->assertEquals(END_MARKER + 1, $endLine);
    }

    public function testGetFileName()
    {
        $fileName = $this->parsedRefFileNamespace->getFileName();
        $this->assertEquals(FILE_NAME, $fileName);
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
        $this->assertEquals(NAMESPACE_NAME, $namespaceName);
    }

    public function testGetNameOfGlobalNamespace()
    {
        $fileName = stream_resolve_include_path(__DIR__ . self::STUB_GLOBAL_FILE);
        $reflectionFile = new ReflectionFile($fileName);

        $reflectionFileNamespace = $reflectionFile->getFileNamespace('');

        $this->assertSame('', $reflectionFileNamespace->getName());
    }

    public function testGetNamespaceAliases()
    {
        $expectedAliases = [
            'SomeClass\WithoutAlias' => 'WithoutAlias',
            'ReflectionClass'        => 'UnusedReflectionClass',
            'PhpParser\Node'         => 'UnusedNode',
            'PhpParser\Node\Expr'    => 'UnusedNodeExpr'
        ];

        $realAliases = $this->parsedRefFileNamespace->getNamespaceAliases();
        $this->assertEquals($expectedAliases, $realAliases);
    }

    public function testGetStartLine()
    {
        $startLine = $this->parsedRefFileNamespace->getStartLine();
        $this->assertEquals(START_MARKER - 2, $startLine);
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
