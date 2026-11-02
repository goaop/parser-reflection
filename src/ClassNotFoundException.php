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

namespace Go\ParserReflection;

use InvalidArgumentException;

/**
 * Thrown when the registered locator can not find the file of a class
 */
class ClassNotFoundException extends InvalidArgumentException
{
    public function __construct(public readonly string $className)
    {
        parent::__construct("Class $className was not found by locator");
    }
}
