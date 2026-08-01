<?php
/**
 * Parser Reflection API
 *
 * @copyright Copyright 2025, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */
declare(strict_types=1);

namespace Go\ParserReflection\Stub;

/**
 * This file contains PHP 8.5 syntax and can not be loaded by an older runtime.
 * It is intended to be analyzed statically only, never include it directly.
 *
 * @see https://wiki.php.net/rfc/pipe-operator-v3
 */

class ClassWithPhp85PipeOperator
{
    public function normalize(string $value): string
    {
        return $value |> trim(...) |> strtoupper(...);
    }

    public function splitAndTrim(string $sentence, string $separator = ','): array
    {
        return explode($separator, $sentence)
            |> (static fn(array $parts): array => array_map(trim(...), $parts));
    }

    public static function firstOrNull(array $values): ?string
    {
        return $values |> array_values(...) |> (static fn(array $list): ?string => $list[0] ?? null);
    }
}
