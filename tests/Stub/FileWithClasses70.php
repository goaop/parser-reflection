<?php
/**
 * Parser Reflection API
 *
 * @copyright Copyright 2016, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */
declare(strict_types=0);

namespace Go\ParserReflection\Stub;

// PHP 7+ code
use Go\ParserReflection\{ReflectionMethod, ReflectionProperty as P};

class ClassWithPhp70ScalarTypeHints
{
    public function acceptsInteger(int $value) {}
    public function acceptsString(string $value) {}
    public function acceptsFloat(float $value) {}
    public function acceptsBool(bool $value) {}
    public function acceptsVariadicInteger(int ...$values) {}
    public function acceptsDefaultString(string $class = ReflectionMethod::class, string $name = P::class) {}
    public function acceptsStringDefaultToNull(string $someName = null) {}
}

class ClassWithPhp70ReturnTypeHints
{
    public function returnsInteger() : int {}
    public function returnsString() : string {}
    public function returnsFloat() : float {}
    public function returnsBool() : bool {}
    public function returnsObject() : ReflectionMethod {}
    public function returnsNamedObject() : P {}
}
