<?php
/**
 * Parser Reflection API
 *
 * @copyright Copyright 2015, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\ParserReflection\Stub;

function miscScalarParameters(
    int $acceptsInteger,
    string $acceptsString,
    float $acceptsFloat = \INF,
    boolean $acceptsBool,
    int $acceptsVariadicInteger,
    string ...$acceptsDefaultString
) {
}
