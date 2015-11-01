<?php
/**
 * Created by PhpStorm.
 * User: 1
 * Date: 22.03.2015
 * Time: 12:12
 */

namespace ParserReflection;

use ParserReflection\Traits\InitializationTrait;
use ParserReflection\Traits\ReflectionClassLikeTrait;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\ClassLike;
use ReflectionClass as InternalReflectionClass;

class ReflectionClass extends InternalReflectionClass
{
    use ReflectionClassLikeTrait, InitializationTrait;

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