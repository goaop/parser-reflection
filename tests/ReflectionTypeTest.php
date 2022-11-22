<?php
/** @noinspection PhpUnhandledExceptionInspection */
declare(strict_types=1);

namespace Go\ParserReflection;

use PHPUnit\Framework\TestCase;
use ReflectionClass as BaseReflectionClass;
use ReflectionMethod as BaseReflectionMethod;

class ReflectionTypeTest extends TestCase
{
    protected function setUp(): void
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
     */
    public function testTypeConvertToDisplayTypeWithNativeType()
    {
        $nativeClassRef = new BaseReflectionClass('Go\\ParserReflection\\Stub\\ClassWithScalarTypeHints');
        $nativeMethodRef = $nativeClassRef->getMethod('acceptsDefaultString');
        $this->assertEquals(BaseReflectionMethod::class, get_class($nativeMethodRef));
        $nativeParamRefArr = $nativeMethodRef->getParameters();
        $this->assertCount(2, $nativeParamRefArr);
        $this->assertEquals(\ReflectionParameter::class, get_class($nativeParamRefArr[0]));
        $nativeTypeRef = $nativeParamRefArr[0]->getType();
        // TODO: To prevent deprecation error in tests
        if (PHP_VERSION_ID < 70400) {
            $this->assertEquals('string', (string)$nativeTypeRef);
        } else {
            $this->assertEquals('string', $nativeTypeRef->getName());
        }
        $this->assertStringNotContainsString('\\', get_class($nativeTypeRef));
        $this->assertInstanceOf(\ReflectionType::class, $nativeTypeRef);
        $this->assertEquals('string', ReflectionType::convertToDisplayType($nativeTypeRef));
    }

    /**
     * Testing convertToDisplayType() with native \ReflectionType
     *
     * We're already testing it with Go\ParserReflection\ReflectionType
     * elsewhere.
     */
    public function testTypeConvertToDisplayTypeWithNullableNativeType()
    {
        $nativeClassRef = new BaseReflectionClass('Go\\ParserReflection\\Stub\\ClassWithNullableScalarTypeHints');
        $nativeMethodRef = $nativeClassRef->getMethod('acceptsDefaultString');
        $this->assertEquals(BaseReflectionMethod::class, get_class($nativeMethodRef));
        $nativeParamRefArr = $nativeMethodRef->getParameters();
        $this->assertCount(2, $nativeParamRefArr);
        $this->assertEquals(\ReflectionParameter::class, get_class($nativeParamRefArr[0]));
        $nativeTypeRef = $nativeParamRefArr[0]->getType();
        // TODO: To prevent deprecation error in tests
        if (PHP_VERSION_ID < 70400) {
            $this->assertEquals('string', (string)$nativeTypeRef);
        } else {
            $this->assertEquals('string', $nativeTypeRef->getName());
        }
        $this->assertStringNotContainsString('\\', get_class($nativeTypeRef));
        $this->assertInstanceOf(\ReflectionType::class, $nativeTypeRef);
        $this->assertEquals('?string', ReflectionType::convertToDisplayType($nativeTypeRef));
    }

    /**
     * Testing convertToDisplayType() with native \ReflectionType
     *
     * We're already testing it with Go\ParserReflection\ReflectionType
     * elsewhere.
     */
    public function testTypeConvertToDisplayTypeImplicitlyNullable()
    {
        $nativeClassRef = new BaseReflectionClass('Go\\ParserReflection\\Stub\\ClassWithScalarTypeHints');
        $nativeMethodRef = $nativeClassRef->getMethod('acceptsStringDefaultToNull');
        $this->assertEquals(BaseReflectionMethod::class, get_class($nativeMethodRef));
        $nativeParamRefArr = $nativeMethodRef->getParameters();
        $this->assertCount(1, $nativeParamRefArr);
        $this->assertEquals(\ReflectionParameter::class, get_class($nativeParamRefArr[0]));
        $nativeTypeRef = $nativeParamRefArr[0]->getType();
        $this->assertTrue($nativeTypeRef->allowsNull());
        // TODO: To prevent deprecation error in tests
        if (PHP_VERSION_ID < 70400) {
            $this->assertEquals('string', (string)$nativeTypeRef);
        } else {
            $this->assertEquals('string', $nativeTypeRef->getName());
        }
        $this->assertStringNotContainsString('\\', get_class($nativeTypeRef));
        $this->assertInstanceOf(\ReflectionType::class, $nativeTypeRef);
        $this->assertEquals('?string', ReflectionType::convertToDisplayType($nativeTypeRef));
    }
}
