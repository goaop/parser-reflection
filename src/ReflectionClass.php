<?php
/**
 * Created by PhpStorm.
 * User: 1
 * Date: 22.03.2015
 * Time: 12:12
 */

namespace ParserReflection;

use ParserReflection\Traits\ReflectionClassLikeTrait;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\ClassLike;
use ReflectionClass as InternalReflectionClass;

class ReflectionClass extends InternalReflectionClass
{
    use ReflectionClassLikeTrait;

    /**
     * Is internal reflection is initialized or not
     *
     * @var boolean
     */
    private $isInitialized = false;


    public function __construct($argument, ClassLike $classLikeNode = null)
    {
        $fullClassName       = is_object($argument) ? get_class($argument) : $argument;
        $namespaceParts      = explode('\\', $fullClassName);
        $this->className     = array_pop($namespaceParts);
        $this->namespaceName = join('\\', $namespaceParts);

        $this->classLikeNode = $classLikeNode ?: Engine::parseClass($fullClassName);
    }

    /**
     * Initializes internal reflection for calling misc runtime methods
     */
    public function initializeInternalReflection()
    {
        if (!$this->isInitialized) {
            parent::__construct($this->getName());
            $this->isInitialized = true;
        }
    }

    /**
     * Returns the status of initialization status for internal object
     *
     * @return bool
     */
    public function isInitialized()
    {
        return $this->isInitialized;
    }
}