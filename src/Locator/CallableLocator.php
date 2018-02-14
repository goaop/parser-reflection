<?php
/**
 * Parser Reflection API
 *
 * @copyright Copyright 2015, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\ParserReflection\Locator;

use Go\ParserReflection\LocatorInterface;

/**
 * Locator, that can find a file for the given class name by asking composer
 */
class CallableLocator implements LocatorInterface
{
    /**
     * @var callable
     */
    private $callable;

    public function __construct(callable $callable)
    {
        $this->callable = $callable;
    }

    /**
     * Returns a path to the file for given class name
     *
     * @param string $className Name of the class
     *
     * @return string|false Path to the file with given class or false if not found
     */
    public function locateClass($className)
    {
        return call_user_func($this->callable, ltrim($className, '\\'));
    }
}
