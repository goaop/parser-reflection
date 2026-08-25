<?php
declare(strict_types=1);
/**
 * Parser Reflection API
 *
 * @copyright Copyright 2026, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * Stub file containing first-class callable syntax (FCC) inside function/method/closure bodies.
 *
 * Unlike FileWithFunctionsFcc.php (which puts FCC into constant-expression positions and thus
 * can not be loaded), this file is perfectly valid runtime PHP and may be included.
 *
 * It is the "PFA-adjacent but already parseable" counterpart of
 * FileWithPartialFunctionApplication86.php: `foo(...)` is represented by PHP-Parser as a call
 * whose single argument is a VariadicPlaceholder, and that representation is intended to be
 * forward-compatible with Partial Function Application. Reflecting function-like bodies that
 * contain it must keep working.
 *
 * @see https://github.com/goaop/parser-reflection/issues/224
 */

namespace Go\ParserReflection\Stub;

function functionWithFccInBody(): \Closure
{
    return strlen(...);
}

class ClassWithFccInBodies
{
    public function methodWithFccInBody(string $separator, int $limit = 2): \Closure
    {
        return str_replace(...);
    }

    public function methodWithFccInClosureBody(): \Closure
    {
        return function (): \Closure {
            return trim(...);
        };
    }

    public function methodWithStaticFccInBody(): \Closure
    {
        return self::helper(...);
    }

    public static function helper(string $value): string
    {
        return $value;
    }
}
