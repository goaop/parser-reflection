<?php
/**
 * Parser Reflection API
 *
 * @copyright Copyright 2015, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace ParserReflection;

use ParserReflection\Traits\ReflectionClassLikeTrait;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\ClassLike;
use ReflectionObject as InternalReflectionObject;

class ReflectionObject extends InternalReflectionObject
{
    use ReflectionClassLikeTrait;

    /**
     * Instance of object
     *
     * @var object
     */
    private $instance;

    public function __construct($instance, ClassLike $classLikeNode = null)
    {
        $this->instance      = $instance;
        $fullClassName       = get_class($instance);
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
        parent::__construct($this->instance);
    }
}