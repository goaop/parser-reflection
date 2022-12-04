<?php
/**
 * Parser Reflection API
 *
 * @copyright Copyright 2022, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 *
 * @noinspection PhpMissingParamTypeInspection
 * @noinspection PhpMissingReturnTypeInspection
 */
declare(strict_types=1);

namespace Go\ParserReflection\Stub {
    function functionWithUnionTypes(int|string $value) {}
    function functionWithUnionTypesWithNull(int|string|null $value) {}
    function functionWithUnionTypesWithDefault(int|string $value = 42) {}

    /**
     * @param int|string $value
     */
    function functionWithUnionTypesInDocBlock($value) {}
}