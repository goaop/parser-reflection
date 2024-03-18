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

use Composer\Autoload\ClassLoader;
use Go\ParserReflection\Instrument\PathResolver;
use Go\ParserReflection\LocatorInterface;
use Go\ParserReflection\ReflectionException;

/**
 * Locator, that can find a file for the given class name by asking composer
 * @see \Go\ParserReflection\Locator\ComposerLocatorTest
 */
class ComposerLocator implements LocatorInterface
{
    /**
     * @var ClassLoader
     */
    private $loader;

    public function __construct(ClassLoader $composerLoader = null)
    {
        if ($composerLoader === null) {
            $loaders = spl_autoload_functions();
            foreach ($loaders as $loader) {
                if (is_array($loader) && $loader[0] instanceof ClassLoader) {
                    $composerLoader = $loader[0];
                    break;
                }
            }
            if ($composerLoader === null) {
                throw new ReflectionException('Can not found a correct composer loader');
            }
        }
        $this->loader = $composerLoader;
    }

    /**
     * Returns a path to the file for given class name
     *
     * @param string $className Name of the class
     **/
    public function locateClass(string $className): false|string
    {
        $filePath = $this->loader->findFile(ltrim($className, '\\'));
        if (!empty($filePath)) {
            $filePath = PathResolver::realpath($filePath);
        }

        return $filePath;
    }
}
