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

use ParserReflection\Traits\InitializationTrait;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\Stmt\PropertyProperty;
use ReflectionProperty as BaseReflectionProperty;

/**
 * AST-based reflection for class property
 */
class ReflectionProperty extends BaseReflectionProperty
{
    use InitializationTrait;

    /**
     * Type of property node
     *
     * @var Property
     */
    private $propertyTypeNode;

    /**
     * Concrete property node
     *
     * @var PropertyProperty
     */
    private $propertyNode;

    /**
     * Name of the class
     *
     * @var string
     */
    private $className;

    /**
     * Initializes a reflection for the property
     *
     * @param string $className Name of the class with properties
     * @param string $propertyName Name of the property to reflect
     * @param Property $propertyType Property type definition node
     * @param PropertyProperty $propertyNode Concrete property definition (value, name)
     */
    public function __construct(
        $className,
        $propertyName,
        Property $propertyType = null,
        PropertyProperty $propertyNode = null)
    {
        $this->className    = $className;
        if (!$propertyType || !$propertyNode) {
            list ($propertyType, $propertyNode) = ReflectionEngine::parseClassProperty($className, $propertyName);
        }

        $this->propertyTypeNode = $propertyType;
        $this->propertyNode     = $propertyNode;
    }

    /**
     * Emulating original behaviour of reflection
     */
    public function __debugInfo()
    {
        return array(
            'name'  => $this->propertyNode->name,
            'class' => $this->className
        );
    }

    /**
     * {@inheritDoc}
     */
    public function isPublic()
    {
        return $this->propertyTypeNode->isPublic();
    }

    /**
     * {@inheritDoc}
     */
    public function isPrivate()
    {
        return $this->propertyTypeNode->isPrivate();
    }

    /**
     * {@inheritDoc}
     */
    public function isProtected()
    {
        return $this->propertyTypeNode->isProtected();
    }

    /**
     * {@inheritDoc}
     */
    public function isStatic()
    {
        return $this->propertyTypeNode->isStatic();
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
    public function setAccessible($accessible)
    {
        $this->initializeInternalReflection();

        parent::setAccessible($accessible);
    }

    /**
     * @inheritDoc
     */
    public function getName()
    {
        return $this->propertyNode->name;
    }

    /**
     * @inheritDoc
     */
    public function getValue($object = null)
    {
        $this->initializeInternalReflection();

        return parent::getValue($object);
    }

    /**
     * @inheritDoc
     */
    public function setValue($object, $value = null)
    {
        $this->initializeInternalReflection();

        parent::setValue($object, $value);
    }

    /**
     * @inheritDoc
     */
    public function isDefault()
    {
        return isset($this->propertyNode->default);
    }

    /**
     * @inheritDoc
     */
    public function getDocComment()
    {
        $docBlock = $this->propertyTypeNode->getDocComment();

        return $docBlock ? $docBlock->getText() : false;
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