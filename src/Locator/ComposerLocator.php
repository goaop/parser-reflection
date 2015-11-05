<?php

namespace ParserReflection\Locator;


use Composer\Autoload\ClassLoader;
use ParserReflection\LocatorInterface;
use ParserReflection\ReflectionException;

class ComposerLocator implements LocatorInterface
{
    /**
     * @var ClassLoader
     */
    private $loader;

    public function __construct(ClassLoader $loader = null)
    {
        if (!$loader) {
            $loaders = spl_autoload_functions();
            foreach ($loaders as $loader) {
                if (is_array($loader) && $loader[0] instanceof ClassLoader) {
                    $loader = $loader[0];
                }
            }
            if (!$loader) {
                throw new ReflectionException("Can not found a correct composer loader");
            }
        }
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