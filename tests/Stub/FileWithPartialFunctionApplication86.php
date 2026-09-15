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
 * Stub file containing PHP 8.6 Partial Function Application (PFA) placeholders.
 *
 * Since nikic/php-parser 5.9 the `?` placeholder parses into an ArgPlaceholder node, so this file
 * is the positive fixture for issue #224 and reflects cleanly on every supported host runtime.
 *
 * It is still NOT valid PHP 8.5 at runtime (PFA is a compile error before PHP 8.6), therefore:
 *   - it must only be included/required behind a PHP_VERSION_ID >= 80600 guard;
 *   - it must NOT be listed in AbstractTestCase::getFilesToAnalyze(), because every parity data
 *     provider includes those files eagerly.
 *
 * @see https://github.com/goaop/parser-reflection/issues/224
 */

namespace Go\ParserReflection\Stub;

/**
 * Function whose body builds a partial application of an internal function.
 */
function functionWithPartialApplicationInBody(): \Closure
{
    return str_replace(' ', '-', ?);
}

/**
 * Function that mixes a bound argument with the "all remaining arguments" placeholder.
 */
function functionWithTrailingVariadicPlaceholder(): \Closure
{
    return str_replace(' ', '-', ...);
}

/**
 * Class with methods and closures using PFA placeholders inside their bodies.
 */
class ClassWithPartialFunctionApplication
{
    public function methodWithPartialApplication(): \Closure
    {
        return str_pad(?, 10, '.');
    }

    public function closureWithPartialApplication(): \Closure
    {
        return function (): \Closure {
            return implode(', ', ?);
        };
    }

    public function staticCallWithPartialApplication(): \Closure
    {
        return self::helper(?, 1);
    }

    public static function helper(string $value, int $times): string
    {
        return str_repeat($value, $times);
    }
}
