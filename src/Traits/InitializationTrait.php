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


trait InitializationTrait
{
    /**
     * Is internal reflection is initialized or not
     *
     * @var boolean
     */
    private $isInitialized = false;

    /**
     * Initializes internal reflection for calling misc runtime methods
     */
    public function initializeInternalReflection()
    {
        if (!$this->isInitialized) {
            $this->__initialize();
            $this->isInitialized = true;
        }
    }

    /**
     * Returns the status of initialization status for internal object
     *
     * @return bool
     */
    public function __isInitialized()
    {
        return $this->isInitialized;
    }

    /**
     * Implementation of internal reflection initialization
     *
     * @return void
     */
    abstract protected function __initialize();
}
