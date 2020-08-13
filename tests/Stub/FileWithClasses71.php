<?php
/**
 * Parser Reflection API
 *
 * @copyright Copyright 2016, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */
declare(strict_types=1);

namespace Go\ParserReflection\Stub;

use Go\ParserReflection\{ReflectionMethod, ReflectionProperty as P};

// PHP 7.1+ code

class ClassWithNullableScalarTypeHints
{
    public function acceptsInteger(?int $value) {}
    public function acceptsString(?string $value) {}
    public function acceptsFloat(?float $value) {}
    public function acceptsBool(?bool $value) {}
    public function acceptsVariadicInteger(?int ...$values) {}
    public function acceptsDefaultString(?string $class = ReflectionMethod::class, ?string $name = P::class) {}
}

class ClassWithNullableReturnTypeHints
{
    public function returnsInteger() : ?int {}
    public function returnsString() : ?string {}
    public function returnsFloat() : ?float {}
    public function returnsBool() : ?bool {}
    public function returnsObject() : ?ReflectionMethod {}
    public function returnsNamedObject() : ?P {}
}

class ClassWithPhp71Features
{
    /**
     * Description for PUBLIC_CONST_A
     */
    const PUBLIC_CONST_A = 1;

    /**
     * Description for PUBLIC_CONST_B
     */
    public const PUBLIC_CONST_B = 2;

    /**
     * Description for PROTECTED_CONST
     */
    protected const PROTECTED_CONST = 3;

    /**
     * Description for PRIVATE_CONST
     */
    private const PRIVATE_CONST = 4;

    public const CALCULATED_CONST = 1 + 1;

    public function returnsVoid() : void {}
    public function acceptsIterable(iterable $iterable) : iterable {}
}
