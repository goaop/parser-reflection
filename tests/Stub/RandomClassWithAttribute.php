<?php
declare(strict_types=1);
/**
 * Parser Reflection API
 *
 * @copyright Copyright 2015, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\ParserReflection\Stub;

use JetBrains\PhpStorm\ArrayShape;
use JetBrains\PhpStorm\Deprecated;

#[Deprecated('some arg')]
class RandomClassWithAttribute
{
    #[ArrayShape([
        'token' => 'string',
        'code' => 'integer'
    ])]
    public $foo;
}

throw new \RuntimeException('test');