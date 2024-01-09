<?php
declare(strict_types=1);

namespace Go\ParserReflection;

use PHPUnit\Framework\TestCase;

class ReflectionTypeTest extends TestCase
{
    protected function setUp(): void
    {
        include_once (__DIR__ . '/Stub/FileWithClasses70.php');
        include_once (__DIR__ . '/Stub/FileWithClasses71.php');
    }

    /**
     * Testing convertToDisplayType() with native \ReflectionType
     *
     * We're already testing it with Go\ParserReflection\ReflectionType
     * elsewhere.
     */
    public function testTypeConvertToDisplayTypeWithNativeType()
    {
        $nativeClassRef = new \ReflectionClass('Go\\ParserReflection\\Stub\\ClassWithScalarTypeHints');
        $nativeMethodRef = $nativeClassRef->getMethod('acceptsDefaultString');
        $this->assertEquals(\ReflectionMethod::class, get_class($nativeMethodRef));
        $nativeParamRefArr = $nativeMethodRef->getParameters();
        $this->assertCount(2, $nativeParamRefArr);
        $this->assertEquals(\ReflectionParameter::class, get_class($nativeParamRefArr[0]));
        $nativeTypeRef = $nativeParamRefArr[0]->getType();
        $this->assertEquals('string', $nativeTypeRef->getName());
        $this->assertStringNotContainsString('\\', get_class($nativeTypeRef));
        $this->assertInstanceOf(\ReflectionType::class, $nativeTypeRef);
        $this->assertEquals('string', \Go\ParserReflection\ReflectionType::convertToDisplayType($nativeTypeRef));
    }

    /**
     * Testing convertToDisplayType() with native \ReflectionType
     *
     * We're already testing it with Go\ParserReflection\ReflectionType
     * elsewhere.
     */
    public function testTypeConvertToDisplayTypeWithNullableNativeType()
    {
        $nativeClassRef = new \ReflectionClass('Go\\ParserReflection\\Stub\\ClassWithNullableScalarTypeHints');
        $nativeMethodRef = $nativeClassRef->getMethod('acceptsDefaultString');
        $this->assertEquals(\ReflectionMethod::class, get_class($nativeMethodRef));
        $nativeParamRefArr = $nativeMethodRef->getParameters();
        $this->assertCount(2, $nativeParamRefArr);
        $this->assertEquals(\ReflectionParameter::class, get_class($nativeParamRefArr[0]));
        $nativeTypeRef = $nativeParamRefArr[0]->getType();
        $this->assertEquals('string', $nativeTypeRef->getName());
        $this->assertStringNotContainsString('\\', get_class($nativeTypeRef));
        $this->assertInstanceOf(\ReflectionType::class, $nativeTypeRef);
        $this->assertEquals('string or NULL', \Go\ParserReflection\ReflectionType::convertToDisplayType($nativeTypeRef));
    }

    /**
     * Testing convertToDisplayType() with native \ReflectionType
     *
     * We're already testing it with Go\ParserReflection\ReflectionType
     * elsewhere.
     */
    public function testTypeConvertToDisplayTypeImplicitlyNullable()
    {
        $nativeClassRef = new \ReflectionClass('Go\\ParserReflection\\Stub\\ClassWithScalarTypeHints');
        $nativeMethodRef = $nativeClassRef->getMethod('acceptsStringDefaultToNull');
        $this->assertEquals(\ReflectionMethod::class, get_class($nativeMethodRef));
        $nativeParamRefArr = $nativeMethodRef->getParameters();
        $this->assertCount(1, $nativeParamRefArr);
        $this->assertEquals(\ReflectionParameter::class, get_class($nativeParamRefArr[0]));
        $nativeTypeRef = $nativeParamRefArr[0]->getType();
        $this->assertTrue($nativeTypeRef->allowsNull());
        $this->assertEquals('string', $nativeTypeRef->getName());
        $this->assertStringNotContainsString('\\', get_class($nativeTypeRef));
        $this->assertInstanceOf(\ReflectionType::class, $nativeTypeRef);
        $this->assertEquals('string or NULL', \Go\ParserReflection\ReflectionType::convertToDisplayType($nativeTypeRef));
    }
}
