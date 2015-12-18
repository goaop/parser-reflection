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

use Go\ParserReflection\Traits\ReflectionClassLikeTrait;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\ClassLike;
use ReflectionClass as InternalReflectionClass;

/**
 * AST-based reflection class
 */
class ReflectionClass extends InternalReflectionClass
{
    use ReflectionClassLikeTrait;

    /**
     * Initializes reflection instance
     *
     * @param string|object $argument Class name or instance of object
     * @param ClassLike $classLikeNode AST node for class
     */
    public function __construct($argument, ClassLike $classLikeNode = null)
    {
        $fullClassName       = is_object($argument) ? get_class($argument) : $argument;
        $namespaceParts      = explode('\\', $fullClassName);
        $this->className     = array_pop($namespaceParts);
        $this->namespaceName = join('\\', $namespaceParts);

        $this->classLikeNode = $classLikeNode ?: ReflectionEngine::parseClass($fullClassName);
    }

    /**
     * Implementation of internal reflection initialization
     *
     * @return void
     */
    protected function __initialize()
    {
        parent::__construct($this->getName());
    }
}
