<?php
/**
 * Created by PhpStorm.
 * User: 1
 * Date: 22.03.2015
 * Time: 12:12
 */

namespace ParserReflection;

use PhpParser\Node\Stmt\ClassMethod;
use ReflectionMethod as BaseReflectionMethod;

class ReflectionMethod extends BaseReflectionMethod
{

    /**
     * Method node
     *
     * @var ClassMethod
     */
    private $classMethodNode;

    /**
     * Is internal reflection is initialized or not
     *
     * @var boolean
     */
    private $isInitialized = false;

    /**
     * @var array|ReflectionParameters[]
     */
    protected $parameters;

    /**
     * @var ReflectionClass
     */
    private $reflectionClass;

    public function __construct(ClassMethod $classMethodNode, ReflectionClass $reflectionClass)
    {
        $this->classMethodNode = $classMethodNode;
        $this->reflectionClass = $reflectionClass;
    }

    /**
     * {@inheritDoc}
     */
    public function isPublic()
    {
        return $this->classMethodNode->isPublic();
    }

    /**
     * {@inheritDoc}
     */
    public function isPrivate()
    {
        return $this->classMethodNode->isPrivate();
    }

    /**
     * {@inheritDoc}
     */
    public function isProtected()
    {
        return $this->classMethodNode->isProtected();
    }

    /**
     * {@inheritDoc}
     */
    public function isAbstract()
    {
        return $this->classMethodNode->isAbstract();
    }

    /**
     * {@inheritDoc}
     */
    public function isFinal()
    {
        return $this->classMethodNode->isFinal();
    }

    /**
     * {@inheritDoc}
     */
    public function isStatic()
    {
        return $this->classMethodNode->isStatic();
    }

    /**
     * {@inheritDoc}
     */
    public function isConstructor()
    {
        return $this->classMethodNode->name == '__construct';
    }

    /**
     * {@inheritDoc}
     */
    public function isDestructor()
    {
        return $this->classMethodNode->name == '__destruct';
    }

    /**
     * {@inheritDoc}
     */
    public function isUserDefined()
    {
        // always defined by user, because we parse the source code
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function isInternal()
    {
        // never can be an internal method
        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function isClosure()
    {
        // method in the class can not be a closure at all
        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function isDeprecated()
    {
        // userland method can not be deprecated
        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function getShortName()
    {
        return $this->classMethodNode->name;
    }

    /**
     * {@inheritDoc}
     */
    public function getName()
    {
        return $this->classMethodNode->name;
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
        return $this->reflectionClass;
    }

    /**
     * Initializes internal reflection for calling misc runtime methods
     */
    public function initializeInternalReflection()
    {
        if (!$this->isInitialized) {
            parent::__construct($this->reflectionClass->getName(), $this->getName());
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