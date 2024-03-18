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
        include_once (__DIR__ . '/Stub/FileWithClasses80.php');
    }

    /**
     * Testing convertToDisplayType() with native \ReflectionType
     *
     * We're already testing it with Go\ParserReflection\ReflectionType
     * elsewhere.
     */
    public function testTypeConvertToDisplayTypeWithNativeType(): void
    {
        $nativeClassRef = new \ReflectionClass('Go\\ParserReflection\\Stub\\ClassWithPhp70ScalarTypeHints');
        $nativeMethodRef = $nativeClassRef->getMethod('acceptsDefaultString');
        $this->assertInstanceOf(\ReflectionMethod::class, $nativeMethodRef);
        $nativeParamRefArr = $nativeMethodRef->getParameters();
        $this->assertCount(2, $nativeParamRefArr);
        $this->assertInstanceOf(\ReflectionParameter::class, $nativeParamRefArr[0]);
        $nativeTypeRef = $nativeParamRefArr[0]->getType();
        $this->assertSame('string', $nativeTypeRef->getName());
        $this->assertStringNotContainsString('\\', get_class($nativeTypeRef));
        $this->assertInstanceOf(\ReflectionType::class, $nativeTypeRef);
        $this->assertSame('string', \Go\ParserReflection\ReflectionType::convertToDisplayType($nativeTypeRef));
    }

    /**
     * Testing convertToDisplayType() with native \ReflectionType
     *
     * We're already testing it with Go\ParserReflection\ReflectionType
     * elsewhere.
     */
    public function testTypeConvertToDisplayTypeWithNullableNativeType(): void
    {
        $nativeClassRef = new \ReflectionClass('Go\\ParserReflection\\Stub\\ClassWithPhp71NullableScalarTypeHints');
        $nativeMethodRef = $nativeClassRef->getMethod('acceptsDefaultString');
        $this->assertInstanceOf(\ReflectionMethod::class, $nativeMethodRef);
        $nativeParamRefArr = $nativeMethodRef->getParameters();
        $this->assertCount(2, $nativeParamRefArr);
        $this->assertInstanceOf(\ReflectionParameter::class, $nativeParamRefArr[0]);
        $nativeTypeRef = $nativeParamRefArr[0]->getType();
        $this->assertSame('string', $nativeTypeRef->getName());
        $this->assertStringNotContainsString('\\', get_class($nativeTypeRef));
        $this->assertInstanceOf(\ReflectionType::class, $nativeTypeRef);
        $this->assertSame('?string', \Go\ParserReflection\ReflectionType::convertToDisplayType($nativeTypeRef));
    }

    /**
     * Testing convertToDisplayType() with native \ReflectionType
     *
     * We're already testing it with Go\ParserReflection\ReflectionType
     * elsewhere.
     */
    public function testTypeConvertToDisplayTypeImplicitlyNullable(): void
    {
        $nativeClassRef = new \ReflectionClass('Go\\ParserReflection\\Stub\\ClassWithPhp70ScalarTypeHints');
        $nativeMethodRef = $nativeClassRef->getMethod('acceptsStringDefaultToNull');
        $this->assertInstanceOf(\ReflectionMethod::class, $nativeMethodRef);
        $nativeParamRefArr = $nativeMethodRef->getParameters();
        $this->assertCount(1, $nativeParamRefArr);
        $this->assertInstanceOf(\ReflectionParameter::class, $nativeParamRefArr[0]);
        $nativeTypeRef = $nativeParamRefArr[0]->getType();
        $this->assertTrue($nativeTypeRef->allowsNull());
        $this->assertSame('string', $nativeTypeRef->getName());
        $this->assertStringNotContainsString('\\', get_class($nativeTypeRef));
        $this->assertInstanceOf(\ReflectionType::class, $nativeTypeRef);
        $this->assertSame('?string', \Go\ParserReflection\ReflectionType::convertToDisplayType($nativeTypeRef));
    }

    /**
     * Testing convertToDisplayType() with native \ReflectionType
     *
     * We're already testing it with Go\ParserReflection\ReflectionType
     * elsewhere.
     */
    public function testTypeConvertToDisplayTypeImplicitlyUnionNullable(): void
    {
        $nativeClassRef = new \ReflectionClass('Go\\ParserReflection\\Stub\\ClassWithPhp80Features');
        $nativeMethodRef = $nativeClassRef->getMethod('acceptsStringArrayDefaultToNull');
        $this->assertInstanceOf(\ReflectionMethod::class, $nativeMethodRef);
        $nativeParamRefArr = $nativeMethodRef->getParameters();
        $this->assertCount(1, $nativeParamRefArr);
        $this->assertInstanceOf(\ReflectionParameter::class, $nativeParamRefArr[0]);
        $nativeTypeRef = $nativeParamRefArr[0]->getType();
        $this->assertTrue($nativeTypeRef->allowsNull());
        $this->assertSame('array|string|null', (string) $nativeTypeRef);
        $this->assertStringNotContainsString('\\', get_class($nativeTypeRef));
        $this->assertInstanceOf(\ReflectionType::class, $nativeTypeRef);
        $this->assertSame('array|string|null', \Go\ParserReflection\ReflectionType::convertToDisplayType($nativeTypeRef));
    }
}
