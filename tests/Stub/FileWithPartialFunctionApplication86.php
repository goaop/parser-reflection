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
 * WARNING: this file is intentionally NOT valid PHP 8.5 source and it can NOT be parsed by the
 * currently required nikic/php-parser (5.8.0 has no grammar for the `?` argument placeholder).
 *
 * Therefore this file:
 *   - must NEVER be included/required (it would be a fatal parse error on a PHP 8.5 runtime);
 *   - must NEVER be listed in AbstractTestCase::getFilesToAnalyze(), because every parity data
 *     provider parses those files eagerly;
 *   - is only ever read as raw text by Php86PartialFunctionApplicationTest, which asserts that
 *     the engine reports a clear parse error instead of silently producing a corrupted AST.
 *
 * Once nikic/php-parser gains PFA support, this stub becomes the positive fixture for
 * issue #224: the constraint gets bumped, the engine picks the grammar up automatically via
 * ParserFactory::createForNewestSupportedVersion(), and the assertions here flip from
 * "raises a parse error" to "reflects cleanly".
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
