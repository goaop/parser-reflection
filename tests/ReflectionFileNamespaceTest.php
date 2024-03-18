<?php
declare(strict_types=1);

namespace Go\ParserReflection;

use Go\ParserReflection\Stub\TestNamespaceClassFoo;
use PHPUnit\Framework\TestCase;

class ReflectionFileNamespaceTest extends TestCase
{
    public const STUB_FILE = '/Stub/FileWithNamespaces.php';
    public const STUB_GLOBAL_FILE = '/Stub/FileWithGlobalNamespace.php';
    protected ReflectionFileNamespace $parsedRefFileNamespace;

    protected function setUp(): void
    {
        $fileName = stream_resolve_include_path(__DIR__ . self::STUB_FILE);
        $fileNode = ReflectionEngine::parseFile($fileName);

        $reflectionFile = new ReflectionFile($fileName, $fileNode);

        $parsedFileNamespace          = $reflectionFile->getFileNamespace('Go\ParserReflection\Stub');
        $this->parsedRefFileNamespace = $parsedFileNamespace;

        include_once $fileName;
    }

    public function testGetClass(): void
    {
        $refClass = $this->parsedRefFileNamespace->getClass(TestNamespaceClassFoo::class);
        $this->assertInstanceOf(\ReflectionClass::class, $refClass);
        $this->assertSame(TestNamespaceClassFoo::class, $refClass->name);

        $this->expectException(ReflectionException::class);
        $this->expectExceptionMessageMatches('/^Could not find the class Unknown in the file/');
        $this->parsedRefFileNamespace->getClass('Unknown');
    }

    public function testGetClasses(): void
    {
        $refClasses = $this->parsedRefFileNamespace->getClasses();
        $this->assertCount(2, $refClasses);
    }

    public function testGetConstant(): void
    {
        $constValue = $this->parsedRefFileNamespace->getConstant('NAMESPACE_NAME');
        $this->assertNotFalse($constValue);
        $this->assertSame(\Go\ParserReflection\Stub\NAMESPACE_NAME, $constValue);

        $this->expectException(ReflectionException::class);
        $this->expectExceptionMessageMatches('/^Could not find the constant Unknown in the file/');
        $this->parsedRefFileNamespace->getConstant('Unknown');
    }

    public function testGetConstants(): void
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

        $constValue = $this->parsedRefFileNamespace->getConstants(false);
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

    public function testGetConstantsCacheIndependence(): void
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
        $this->expectException(ReflectionException::class);
        $this->expectExceptionMessageMatches('/^Could not find the constant INT_CONST in the file/');
        $this->parsedRefFileNamespace->getConstant('INT_CONST');
    }

    public function testGetGlobalConstants(): void
    {
        $fileName = stream_resolve_include_path(__DIR__ . self::STUB_GLOBAL_FILE);
        $reflectionFile = new ReflectionFile($fileName);

        $reflectionFileNamespace = $reflectionFile->getFileNamespace('');

        $this->assertSame(
            [
                // Scalar types are handled.
                'INT_CONST' => 5,
                'STRING_CONST' => 'text',
                'BOOLEAN_CONST' => true,

                // Expressions are handled partially.
                'EXPRESSION_CONST' => false,
                'FUNCTION_CONST' => mktime(hour: 12, minute: 33, second: 00),
                'AAAAAAAAAA' => true
            ],
            $reflectionFileNamespace->getConstants(true)
        );
    }

    public function testGetDocComment(): void
    {
        $docComment = $this->parsedRefFileNamespace->getDocComment();
        $this->assertNotEmpty($docComment);
    }

    public function testGetEndLine(): void
    {
        $endLine = $this->parsedRefFileNamespace->getEndLine();
        $this->assertSame(\Go\ParserReflection\Stub\END_MARKER + 1, $endLine);
    }

    public function testGetFileName(): void
    {
        $fileName = $this->parsedRefFileNamespace->getFileName();
        $this->assertEquals(\Go\ParserReflection\Stub\FILE_NAME, $fileName);
    }

    public function testGetFunction(): void
    {
        $refFunction = $this->parsedRefFileNamespace->getFunction('testFunctionBar');
        $this->assertInstanceOf(\ReflectionFunction::class, $refFunction);
        $this->assertSame('testFunctionBar', $refFunction->name);

        $this->expectException(ReflectionException::class);
        $this->expectExceptionMessageMatches('/^Could not find the function Unknown in the file/');
        $this->parsedRefFileNamespace->getFunction('Unknown');
    }

    public function testGetFunctions(): void
    {
        $refFunctions = $this->parsedRefFileNamespace->getFunctions();
        $this->assertCount(1, $refFunctions);
    }

    public function testGetName(): void
    {
        $namespaceName = $this->parsedRefFileNamespace->getName();
        $this->assertSame(\Go\ParserReflection\Stub\NAMESPACE_NAME, $namespaceName);
    }

    public function testGetNameOfGlobalNamespace(): void
    {
        $fileName = stream_resolve_include_path(__DIR__ . self::STUB_GLOBAL_FILE);
        $reflectionFile = new ReflectionFile($fileName);

        $reflectionFileNamespace = $reflectionFile->getFileNamespace('');

        $this->assertSame('', $reflectionFileNamespace->getName());
    }

    public function testGetNamespaceAliases(): void
    {
        $expectedAliases = [
            'SomeClass\WithoutAlias' => 'WithoutAlias',
            'ReflectionClass'        => 'UnusedReflectionClass',
            'PhpParser\Node'         => 'UnusedNode',
            'PhpParser\Node\Expr'    => 'UnusedNodeExpr'
        ];

        $realAliases = $this->parsedRefFileNamespace->getNamespaceAliases();
        $this->assertSame($expectedAliases, $realAliases);
    }

    public function testGetStartLine(): void
    {
        $startLine = $this->parsedRefFileNamespace->getStartLine();
        $this->assertSame(\Go\ParserReflection\Stub\START_MARKER - 2, $startLine);
    }

    public function testHasClass(): void
    {
        $hasClass = $this->parsedRefFileNamespace->hasClass('Unknown');
        $this->assertFalse($hasClass);

        $hasClass = $this->parsedRefFileNamespace->hasClass(TestNamespaceClassFoo::class);
        $this->assertTrue($hasClass);
    }

    public function testHasConstant(): void
    {
        $hasConstant = $this->parsedRefFileNamespace->hasConstant('Unknown');
        $this->assertFalse($hasConstant);

        $hasConstant = $this->parsedRefFileNamespace->hasConstant('NAMESPACE_NAME');
        $this->assertTrue($hasConstant);
    }

    public function testHasFunction(): void
    {
        $hasFunction = $this->parsedRefFileNamespace->hasFunction('Unknown');
        $this->assertFalse($hasFunction);

        $hasFunction = $this->parsedRefFileNamespace->hasFunction('testFunctionBar');
        $this->assertTrue($hasFunction);
    }
}
