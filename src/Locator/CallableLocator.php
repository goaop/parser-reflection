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

namespace Go\ParserReflection\Locator;

use Closure;
use Go\ParserReflection\LocatorInterface;

/**
 * Locator, that can find a file for the given class name by asking composer
 * @see \Go\ParserReflection\Locator\CallableLocatorTest
 */
final readonly class CallableLocator implements LocatorInterface
{

    public function __construct(private Closure $callable)
    {
    }

    /**
     * Returns a path to the file for given class name
     *
     * @param string $className Name of the class
     */
    public function locateClass(string $className): false|string
    {
        $result = ($this->callable)(ltrim($className, '\\'));

        return is_string($result) ? $result : false;
    }
}
