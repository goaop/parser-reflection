<?php
/**
 * Parser Reflection API
 *
 * @copyright Copyright 2022, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 *
 * @noinspection PhpIllegalPsrClassPathInspection
 * @noinspection PhpMissingReturnTypeInspection
 * @noinspection PhpPropertyOnlyWrittenInspection
 * @noinspection PhpMissingFieldTypeInspection
 * @noinspection PhpUnusedPrivateMethodInspection
 */
declare(strict_types=1);

namespace Go\ParserReflection\Stub;

// PHP 8.0+ code

use Attribute;

class ClassWithUnionTypes
{
    public function unionType(int|string $value) {}
    public function unionTypeWithNull(int|string|null $value) {}
    public function unionTypeWithDefault(int|string $value = 42) {}
}

class ClassWithConstructorPropertyPromotion
{
    public function __construct(
        public int $id,
        protected string $name,
        private ?string $description = null,
    ) {}
}

#[Attribute]
class ClassWithAttributes {}

#[Attribute]
class CustomAttribute {}

#[CustomAttribute]
class ClassWithCustomAttribute {}

#[Attribute]
class AttributeWithParams
{
    public function __construct(
        public int $param1,
        public string $param2,
        public array $param3,
        public bool $param4,
        public float $param5,
        public int|string $param6,
        public int|string|null $param7,
        public int|string $param8 = 42,
    ) {}
}

#[AttributeWithParams(1, '2', [3], true, 4.5, 6, null)]
class ClassWithAttributeWithParams
{
    #[AttributeWithParams(1, '2', [3], true, 4.5, 6, null)]
    public const CONST_WITH_ATTRIBUTE = 1;

    #[AttributeWithParams(1, '2', [3], true, 4.5, 6, null)]
    protected $paramWithAttribute;

    #[AttributeWithParams(1, '2', [3], true, 4.5, 6, null)]
    private function methodWithAttribute() {}

    public function methodWithAttributeInParam(
        #[AttributeWithParams(1, '2', [3], true, 4.5, 6, null)]
        $param
    ) {}

    #[AttributeWithParams(
        param1: 1,
        param2: '2',
        param3: [3],
        param4: true,
        param5: 4.5,
        param6: 6,
        param7: null,
    )]
    public function methodWithAttributeWithNamedParams() {}
}

#[Attribute(Attribute::TARGET_CLASS)]
class AttributeWithTargetClass {}

#[AttributeWithTargetClass]
class ClassWithAttributeWithTargetClass {}

#[Attribute(Attribute::TARGET_METHOD)]
class AttributeWithTargetMethod {}

class ClassWithAttributeWithTargetMethod
{
    #[AttributeWithTargetMethod]
    public function methodWithAttributeWithTargetMethod() {}
}

#[Attribute(Attribute::TARGET_PROPERTY)]
class AttributeWithTargetProperty {}

class ClassWithAttributeWithTargetProperty
{
    #[AttributeWithTargetProperty]
    public $propertyWithAttributeWithTargetProperty;
}

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class AttributeWithTargetClassAndMethod {}

#[AttributeWithTargetClassAndMethod]
class ClassWithAttributeWithTargetClassAndMethod
{
    #[AttributeWithTargetClassAndMethod]
    public function methodWithAttributeWithTargetClassAndMethod() {}
}
