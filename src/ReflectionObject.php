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

use Go\ParserReflection\Traits\InternalPropertiesEmulationTrait;
use Go\ParserReflection\Traits\ReflectionClassLikeTrait;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\ClassLike;
use ReflectionObject as InternalReflectionObject;

/**
 * AST-based reflection for an object
 *
 * Typically, this class won't be used on parsing level, because if we have an instance of object,
 * then we can initialize a default ReflectionObject for it.
 */
class ReflectionObject extends InternalReflectionObject
{
    use ReflectionClassLikeTrait, InternalPropertiesEmulationTrait;

    /**
     * Instance of object
     *
     * @var object
     */
    private $instance;

    /**
     * Initializes reflection instance
     *
     * @param object $instance Instance of object
     * @param ClassLike $classLikeNode AST node for class definition
     */
    public function __construct($instance, ClassLike $classLikeNode = null)
    {
        $this->instance      = $instance;
        $fullClassName       = get_class($instance);
        $namespaceParts      = explode('\\', $fullClassName);
        $this->className     = array_pop($namespaceParts);
        // Let's unset original read-only property to have a control over it via __get
        unset($this->name);

        $this->namespaceName = join('\\', $namespaceParts);

        $this->classLikeNode = $classLikeNode ?: ReflectionEngine::parseClass($fullClassName);
    }

    /**
     * Emulating original behaviour of reflection
     */
    public function __debugInfo()
    {
        return array(
            'name' => $this->getName()
        );
    }

    /**
     * Implementation of internal reflection initialization
     *
     * @return void
     */
    protected function __initialize()
    {
        parent::__construct($this->instance);
    }
}
