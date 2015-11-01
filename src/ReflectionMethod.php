<?php
/**
 * Created by PhpStorm.
 * User: 1
 * Date: 22.03.2015
 * Time: 12:12
 */

namespace ParserReflection;

use ParserReflection\Traits\InitializationTrait;
use ParserReflection\Traits\ReflectionFunctionLikeTrait;
use PhpParser\Node\Stmt\ClassMethod;
use ReflectionMethod as BaseReflectionMethod;

class ReflectionMethod extends BaseReflectionMethod
{
    use ReflectionFunctionLikeTrait, InitializationTrait;

    /**
     * Name of the class
     *
     * @var string
     */
    private $className;

    public function __construct($className, $methodName, ClassMethod $classMethodNode = null)
    {
        $this->className        = $className;
        $this->functionLikeNode = $classMethodNode ?: Engine::parseClassMethod($className, $methodName);
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
    public function getName()
    {
        return $this->functionLikeNode->name;
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
     * Implementation of internal reflection initialization
     *
     * @return void
     */
    protected function __initialize()
    {
        parent::__construct($this->className, $this->getName());
    }
}