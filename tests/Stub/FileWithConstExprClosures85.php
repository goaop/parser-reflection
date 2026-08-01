<?php
declare(strict_types=1);
/**
 * Parser Reflection API
 *
 * @copyright Copyright 2025, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\ParserReflection\Stub;

/**
 * This file uses closures, arrow functions and first-class callables in constant expressions, which is
 * allowed only since PHP 8.5, therefore it is intended to be analyzed statically only, never include it.
 *
 * @see https://wiki.php.net/rfc/closures_in_const_expr
 */

const CLOSURE_CONST = static function (int $x): int {
    return $x * 2;
};

const ARROW_FUNCTION_CONST = static fn (int $x): int => $x + 10;

const FCC_CONST = \strlen(...);

const UNQUALIFIED_FCC_CONST = strtoupper(...);

const PLAIN_CONST = 'plain';

#[\Attribute]
class ConstExprClosureAttribute
{
    public function __construct(public \Closure $callback)
    {
    }
}

#[ConstExprClosureAttribute(static fn (int $value): int => $value * 5)]
class ClassWithConstExprClosures
{
    public const CALLBACK = static fn (int $value): int => $value + 1;

    public const DOUBLER = static function (int $value): int {
        return $value * 2;
    };

    public const UPPERCASE = \strtoupper(...);

    public const PLAIN = 'simple';

    public \Closure $handler = static fn (string $value): string => \trim($value);

    public function methodWithClosureDefault(\Closure $callback = static function (): int {
        return 1;
    }): int
    {
        return $callback();
    }

    public function methodWithArrowFunctionDefault(
        \Closure $callback = static fn (int $value): int => $value * 3
    ): int {
        return $callback(1);
    }
}
