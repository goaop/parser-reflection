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
class ReflectionExtension extends BaseReflectionExtension implements ReflectionInterface
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
     * @return \ReflectionClass[]
     */
    public function getClasses()
    {
        if (!isset($this->classes)) {
            $this->classes = [];
            foreach (parent::getClasses() as $index => $classReflection) {
                $this->classes[$index] = new ReflectionClass($classReflection->name);
            }
        }

        return $this->classes;
    }

    /**
     * Returns list of reflection functions
     *
     * @return \ReflectionFunction[]
     */
    public function getFunctions()
    {
        if (!isset($this->functions)) {
            $this->functions = [];
            foreach (parent::getFunctions() as $index => $functionReflection) {
                $this->functions[$index] = new ReflectionFunction($functionReflection->name);
            }
        }

        return $this->functions;
    }
}
