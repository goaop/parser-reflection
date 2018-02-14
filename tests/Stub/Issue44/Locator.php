<?php

namespace Stub\Issue44;

use Go\ParserReflection\LocatorInterface;

class Locator implements LocatorInterface
{
    /**
     * @inheritdoc
     */
    public function locateClass($className)
    {
        if ($className === ClassWithNamespace::class) {
            return __DIR__ . '/ClassWithNamespace.php';
        }

        return false;
    }
}
