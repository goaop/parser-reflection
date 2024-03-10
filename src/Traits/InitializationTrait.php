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

namespace Go\ParserReflection\Traits;


trait InitializationTrait
{
    /**
     * Is internal reflection is initialized or not
     */
    private bool $isInitialized = false;

    /**
     * Initializes internal reflection for calling misc runtime methods
     */
    public function initializeInternalReflection(): void
    {
        if (!$this->isInitialized) {
            $this->__initialize();
            $this->isInitialized = true;
        }
    }

    /**
     * Returns the status of initialization status for internal object
     */
    public function __isInitialized(): bool
    {
        return $this->isInitialized;
    }

    /**
     * Implementation of internal reflection initialization
     */
    abstract protected function __initialize(): void;
}
