<?php
/**
 * Parser Reflection API
 *
 * @copyright Copyright 2015, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\ParserReflection;

use ReflectionExtension as BaseReflectionExtension;

/**
 * Returns AST-based reflections from extensions.
 */
class ReflectionExtension extends BaseReflectionExtension implements IReflection
{
    /**
     * @var null|ReflectionClass[] ParsedReflection wrapped classes.
     */
    private $classes;

    /**
     * @var null|ReflectionFunction[] ParsedReflection wrapped functions.
     */
    private $functions;

    /**
     * Has extension been loaded by PHP.
     *
     * @return true
     *     Enabled extensions are always loaded.
     */
    public function wasIncluded()
    {
        return true;
    }

    /**
     * Returns list of reflection classes
     *
     * @return array|\ReflectionClass[]
     */
    public function getClasses()
    {
        if (!isset($this->classes)) {
            $classRefs = parent::getClasses();
            $this->classes = [];
            foreach ($classRefs as $origKey => $eachClass) {
                $this->classes[$origKey] = new ReflectionClass($eachClass->name);
            }
        }

        return $this->classes;
    }

    /**
     * Returns list of reflection functions
     *
     * @return array|\ReflectionFunction[]
     */
    public function getFunctions()
    {
        if (!isset($this->functions)) {
            $funcRefs = parent::getFunctions();
            $this->functions = [];
            foreach ($funcRefs as $origKey => $eachFunc) {
                $this->functions[$origKey] = new ReflectionFunction($eachFunc->name);
            }
        }

        return $this->functions;
    }
}
