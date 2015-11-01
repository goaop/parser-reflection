<?php

namespace ParserReflection\Locator;


use Composer\Autoload\ClassLoader;
use ParserReflection\LocatorInterface;

class ComposerLocator implements LocatorInterface
{
    /**
     * @var ClassLoader
     */
    private $loader;

    public function __construct(ClassLoader $loader)
    {
        $this->loader = $loader;
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
        return $this->loader->findFile($className);
    }
}