<?php
/**
 * Parser Reflection API
 *
 * @copyright Copyright 2022, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 *
 * @noinspection PhpUnused
 * @noinspection PhpIllegalPsrClassPathInspection
 * @noinspection PhpMissingFieldTypeInspection
 * @noinspection PhpMissingReturnTypeInspection
 * @noinspection PhpUnusedPrivateMethodInspection
 * @noinspection PhpMissingParentConstructorInspection
 */
declare(strict_types=1);

namespace Go\ParserReflection\Stub;

// PHP 8.0+ code

use Attribute;

#[Attribute]
class ClassWithAttributes {}

#[
Attribute
] class
ClassWithAttributes2
{
}

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

#[Attribute(Attribute::TARGET_ALL)]
class AttributeWithTargetAll {}

#[AttributeWithTargetAll]
class ClassWithAttributeWithTargetAll
{
    #[AttributeWithTargetAll]
    public $propertyWithAttributeWithTargetAll;

    #[AttributeWithTargetAll]
    public function methodWithAttributeWithTargetAll() {}
}

#[Attribute(Attribute::IS_REPEATABLE | Attribute::TARGET_CLASS)]
class AttributeWithIsRepeatable
{
    public function __construct(
        public int $param1,
        public string $param2,
    ) {}
}

#[AttributeWithIsRepeatable(1, '2')]
#[AttributeWithIsRepeatable(3, '4')]
class ClassWithAttributeWithIsRepeatable {}

#[AttributeWithIsRepeatable(1, '2')]
class AttributeWithOverriddenConstructor extends AttributeWithIsRepeatable
{
    public function __construct(
        public int $param1,
        public string $param2,
        public int $param3,
    ) {}
}
