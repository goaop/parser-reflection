<?php
namespace Go\ParserReflection;

class ReflectionTypeTest extends \PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        if (PHP_VERSION_ID >= 70000) {
            include_once (__DIR__ . '/Stub/FileWithClasses70.php');
        }
        if (PHP_VERSION_ID >= 70100) {
            include_once (__DIR__ . '/Stub/FileWithClasses71.php');
        }
    }

    /**
     * Testing convertToDisplayType() with native \ReflectionType
     *
     * We're already testing it with Go\ParserReflection\ReflectionType
     * elsewhere.
     *
     * @requires PHP 7.0.0
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
        $this->assertEquals('string', (string)$nativeTypeRef);
        $this->assertNotContains('\\', get_class($nativeTypeRef));
        $this->assertInstanceOf(\ReflectionType::class, $nativeTypeRef);
        $this->assertEquals('string', \Go\ParserReflection\ReflectionType::convertToDisplayType($nativeTypeRef));
    }

    /**
     * Testing convertToDisplayType() with native \ReflectionType
     *
     * We're already testing it with Go\ParserReflection\ReflectionType
     * elsewhere.
     *
     * @requires PHP 7.1.0
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
        $this->assertEquals('string', (string)$nativeTypeRef);
        $this->assertNotContains('\\', get_class($nativeTypeRef));
        $this->assertInstanceOf(\ReflectionType::class, $nativeTypeRef);
        $this->assertEquals('string or NULL', \Go\ParserReflection\ReflectionType::convertToDisplayType($nativeTypeRef));
    }

    /**
     * Testing convertToDisplayType() with native \ReflectionType
     *
     * We're already testing it with Go\ParserReflection\ReflectionType
     * elsewhere.
     *
     * @requires PHP 7.0.0
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
        $this->assertEquals('string', (string)$nativeTypeRef);
        $this->assertNotContains('\\', get_class($nativeTypeRef));
        $this->assertInstanceOf(\ReflectionType::class, $nativeTypeRef);
        $this->assertEquals('string or NULL', \Go\ParserReflection\ReflectionType::convertToDisplayType($nativeTypeRef));
    }
}
