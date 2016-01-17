<?php
/**
 * Parser Reflection API
 *
 * @copyright Copyright 2015, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\ParserReflection\Traits;

/**
 * Class for emulating internal properties behaviour
 */
trait InternalPropertiesEmulationTrait
{
    /**
     * Magic method that should be defined to provide an info about internal properties
     *
     * @return array
     */
    abstract public function ___debugInfo();

    /**
     * Magic implementation of properties
     *
     * @param string $propertyName Name of the property to return
     *
     * @return null|mixed
     */
    public function __get($propertyName)
    {
        $internalInfo = $this->___debugInfo();
        if (!isset($internalInfo[$propertyName])) {
            $className = get_class($this);
            trigger_error("Undefined property {$className}::\${$propertyName}");

            return null;
        }

        return $internalInfo[$propertyName];
    }
}
