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

class ClassWithPhp80Features
{
    public function acceptsStringArrayDefaultToNull(array|string $iterable = null) : array {}
}
