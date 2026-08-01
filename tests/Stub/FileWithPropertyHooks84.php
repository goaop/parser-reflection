<?php
/**
 * Parser Reflection API
 *
 * @copyright Copyright 2025, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */
declare(strict_types=1);

namespace Go\ParserReflection\Stub;

/**
 * @see https://wiki.php.net/rfc/property-hooks
 */

class ClassWithHookedAndPlainProperties
{
    private string $storage = 'stored';

    public string $plainProperty = 'plain';

    /**
     * Backed property with both hooks, raw value is stored in the property itself
     */
    public int $backedCounter = 1 {
        get => $this->backedCounter + 1;
        set => $value * 2;
    }

    /**
     * Virtual property with both hooks, there is no backing store at all
     */
    public string $virtualWithBothHooks {
        get => $this->storage;
        set (string $value) {
            $this->storage = strtolower($value);
        }
    }

    /**
     * Virtual property that declares the set hook before the get one
     */
    public string $reversedHookOrder {
        set (string $value) {
            $this->storage = trim($value);
        }
        get => $this->storage;
    }

    /**
     * Virtual property with a single get hook
     */
    public string $virtualReadOnly {
        get => strtoupper($this->storage);
    }

    public function __construct(public string $promotedProperty = 'promoted')
    {
    }
}

interface InterfaceWithAbstractHook
{
    public string $abstractHooked {
        get;
    }
}
