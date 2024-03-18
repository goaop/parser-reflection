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

namespace Go\ParserReflection;

/**
 * Locator is responsible to return a file name for given item, typically class
 */
interface LocatorInterface
{

    /**
     * Returns a path to the file for given class name
     *
     * @param string $className Name of the class (with or without leading '\' FQCN)
     */
    public function locateClass(string $className): false|string;
}
