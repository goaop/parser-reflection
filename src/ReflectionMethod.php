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

use ParserReflection\Traits\ReflectionFunctionLikeTrait;
use PhpParser\Node\Stmt\ClassMethod;
use ReflectionMethod as BaseReflectionMethod;

/**
 * AST-based reflection for the method in a class
 */
class ReflectionMethod extends BaseReflectionMethod
{
    use ReflectionFunctionLikeTrait;

    /**
     * Name of the class
     *
     * @var string
     */
    private $className;

    /**
     * Initializes reflection instance for the method node
     *
     * @param string $className Name of the class
     * @param string $methodName Name of the method
     * @param ClassMethod $classMethodNode AST-node for method
     */
    public function __construct($className, $methodName, ClassMethod $classMethodNode = null)
    {
        //for some reason, ReflectionMethod->getNamespaceName in php always returns '', so we shouldn't use it too
        $this->className        = $className;
        $this->functionLikeNode = $classMethodNode ?: ReflectionEngine::parseClassMethod($className, $methodName);
    }

    /**
     * Emulating original behaviour of reflection
     */
    public function __debugInfo()
    {
        return array(
            'name'  => $this->functionLikeNode->name,
            'class' => $this->className
        );
    }

    /**
     * {@inheritDoc}
     */
    public function isPublic()
    {
        return $this->functionLikeNode->isPublic();
    }

    /**
     * {@inheritDoc}
     */
    public function isPrivate()
    {
        return $this->functionLikeNode->isPrivate();
    }

    /**
     * {@inheritDoc}
     */
    public function isProtected()
    {
        return $this->functionLikeNode->isProtected();
    }

    /**
     * {@inheritDoc}
     */
    public function isAbstract()
    {
        return $this->functionLikeNode->isAbstract();
    }

    /**
     * {@inheritDoc}
     */
    public function isFinal()
    {
        return $this->functionLikeNode->isFinal();
    }

    /**
     * {@inheritDoc}
     */
    public function isStatic()
    {
        return $this->functionLikeNode->isStatic();
    }

    /**
     * {@inheritDoc}
     */
    public function isConstructor()
    {
        return $this->functionLikeNode->name == '__construct';
    }

    /**
     * {@inheritDoc}
     */
    public function isDestructor()
    {
        return $this->functionLikeNode->name == '__destruct';
    }

    /**
     * {@inheritDoc}
     */
    public function getModifiers()
    {
        $modifiers = 0;
        if ($this->isPublic()) {
            $modifiers += self::IS_PUBLIC;
        }
        if ($this->isProtected()) {
            $modifiers += self::IS_PROTECTED;
        }
        if ($this->isPrivate()) {
            $modifiers += self::IS_PRIVATE;
        }
        if ($this->isAbstract()) {
            $modifiers += self::IS_ABSTRACT;
        }
        if ($this->isFinal()) {
            $modifiers += self::IS_FINAL;
        }
        if ($this->isStatic()) {
            $modifiers += self::IS_STATIC;
        }

        return $modifiers;
    }

    /**
     * {@inheritDoc}
     */
    public function getDeclaringClass()
    {
        return new ReflectionClass($this->className);
    }

    /**
     * {@inheritDoc}
     */
    public function getClosure($object)
    {
        $this->initializeInternalReflection();

        return parent::getClosure($object);
    }

    /**
     * {@inheritDoc}
     */
    public function setAccessible($accessible)
    {
        $this->initializeInternalReflection();

        parent::setAccessible($accessible);
    }

    /**
     * {@inheritDoc}
     */
    public function invoke($object, $args = null)
    {
        $this->initializeInternalReflection();

        return call_user_func_array('parent::invoke', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function invokeArgs($object, array $args)
    {
        $this->initializeInternalReflection();

        return parent::invokeArgs($object, $args);
    }

    /**
     * {@inheritDoc}
     */
    public function getPrototype()
    {
        $parent = $this->getDeclaringClass()->getParentClass();
        if (!$parent) {
            throw new ReflectionException("No prototype");
        }

        $prototypeMethod = $parent->getMethod($this->getName());
        if (!$prototypeMethod) {
            throw new ReflectionException("No prototype");
        }

        return $prototypeMethod;
    }


    /**
     * Implementation of internal reflection initialization
     *
     * @return void
     */
    protected function __initialize()
    {
        parent::__construct($this->className, $this->getName());
    }
}