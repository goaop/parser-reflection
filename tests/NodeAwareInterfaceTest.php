<?php
declare(strict_types=1);

namespace Go\ParserReflection;

use Go\ParserReflection\Stub\BackedPhp81EnumHTTPMethods;
use Go\ParserReflection\Stub\ClassWithBackedEnumDefaultValue;
use Go\ParserReflection\Stub\ClassWithPhp81FinalClassConst;
use Go\ParserReflection\Stub\ClassWithPhp81ReadOnlyProperties;
use Go\ParserReflection\Stub\SimplePhp81EnumWithSuit;
use PhpParser\Node;
use PhpParser\Node\Attribute;
use PhpParser\Node\Param;
use PhpParser\Node\PropertyItem;
use PhpParser\Node\Stmt\ClassConst;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Enum_;
use PhpParser\Node\Stmt\EnumCase;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\Namespace_;
use PHPUnit\Framework\TestCase;

/**
 * Tests that every parsed reflection class exposes its underlying AST node
 * through the NodeAwareInterface contract
 *
 * @see NodeAwareInterface
 */
class NodeAwareInterfaceTest extends TestCase
{
    public const STUB_FILE = '/Stub/FileWithClasses81.php';

    protected ReflectionFileNamespace $parsedRefFileNamespace;

    protected function setUp(): void
    {
        $fileName = stream_resolve_include_path(__DIR__ . self::STUB_FILE);
        $fileNode = ReflectionEngine::parseFile($fileName);

        $reflectionFile = new ReflectionFile($fileName, $fileNode);

        $this->parsedRefFileNamespace = $reflectionFile->getFileNamespace('Go\ParserReflection\Stub');

        include_once $fileName;
    }

    /**
     * Asserts that a reflection implements NodeAwareInterface and returns the expected node type
     *
     * @param class-string<Node> $expectedNodeClass
     */
    private function assertNodeAware(object $reflection, string $expectedNodeClass): void
    {
        $this->assertInstanceOf(NodeAwareInterface::class, $reflection);
        $this->assertInstanceOf($expectedNodeClass, $reflection->getNode());
    }

    public function testFileNamespaceIsNodeAware(): void
    {
        $this->assertNodeAware($this->parsedRefFileNamespace, Namespace_::class);
    }

    public function testClassIsNodeAware(): void
    {
        $refClass = $this->parsedRefFileNamespace->getClass(ClassWithPhp81ReadOnlyProperties::class);
        $this->assertNodeAware($refClass, ClassLike::class);
    }

    public function testEnumIsNodeAware(): void
    {
        $refEnum = $this->parsedRefFileNamespace->getEnums()[SimplePhp81EnumWithSuit::class];
        $this->assertNodeAware($refEnum, Enum_::class);
    }

    public function testEnumUnitCaseIsNodeAware(): void
    {
        $refEnum = $this->parsedRefFileNamespace->getEnums()[SimplePhp81EnumWithSuit::class];
        $this->assertNodeAware($refEnum->getCase('Clubs'), EnumCase::class);
    }

    public function testEnumBackedCaseIsNodeAware(): void
    {
        $refEnum = $this->parsedRefFileNamespace->getEnums()[BackedPhp81EnumHTTPMethods::class];
        $this->assertNodeAware($refEnum->getCase('GET'), EnumCase::class);
    }

    public function testMethodIsNodeAware(): void
    {
        $refClass  = $this->parsedRefFileNamespace->getClass(ClassWithBackedEnumDefaultValue::class);
        $refMethod = $refClass->getMethod('getRefusalDescription');
        $this->assertNodeAware($refMethod, ClassMethod::class);
    }

    public function testFunctionIsNodeAware(): void
    {
        $refFunction = $this->parsedRefFileNamespace->getFunction('functionWithPhp81IntersectionType');
        $this->assertNodeAware($refFunction, Function_::class);
    }

    public function testParameterIsNodeAware(): void
    {
        $refClass  = $this->parsedRefFileNamespace->getClass(ClassWithBackedEnumDefaultValue::class);
        $refMethod = $refClass->getMethod('getRefusalDescription');
        [$refParameter] = $refMethod->getParameters();
        $this->assertNodeAware($refParameter, Param::class);
    }

    public function testPropertyIsNodeAware(): void
    {
        $refClass    = $this->parsedRefFileNamespace->getClass(ClassWithPhp81ReadOnlyProperties::class);
        $refProperty = $refClass->getProperty('publicReadonlyInt');
        $this->assertNodeAware($refProperty, PropertyItem::class);
    }

    public function testClassConstantIsNodeAware(): void
    {
        $refClass    = $this->parsedRefFileNamespace->getClass(ClassWithPhp81FinalClassConst::class);
        $refConstant = $refClass->getReflectionConstant('TEST');
        $this->assertNodeAware($refConstant, ClassConst::class);
    }

    public function testEnumCaseConstantIsNodeAware(): void
    {
        $refEnum     = $this->parsedRefFileNamespace->getEnums()[SimplePhp81EnumWithSuit::class];
        $refConstant = $refEnum->getReflectionConstant('Clubs');
        $this->assertNodeAware($refConstant, EnumCase::class);
    }

    public function testAttributeIsNodeAware(): void
    {
        $fileName = stream_resolve_include_path(__DIR__ . '/Stub/FileWithFunction80.php');
        $fileNode = ReflectionEngine::parseFile($fileName);

        $reflectionFile = new ReflectionFile($fileName, $fileNode);
        $fileNamespace  = $reflectionFile->getFileNamespace('Go\ParserReflection\Stub');

        $refFunction  = $fileNamespace->getFunction('function_with_attribute');
        $attributes   = $refFunction->getAttributes();

        $this->assertNotEmpty($attributes);
        $this->assertNodeAware($attributes[0], Attribute::class);
    }
}
