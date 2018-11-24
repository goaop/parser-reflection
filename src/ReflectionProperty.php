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

use Go\ParserReflection\Traits\InitializationTrait;
use Go\ParserReflection\Traits\InternalPropertiesEmulationTrait;
use Go\ParserReflection\ValueResolver\NodeExpressionResolver;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\Stmt\PropertyProperty;
use ReflectionProperty as BaseReflectionProperty;

/**
 * AST-based reflection for class property
 */
class ReflectionProperty extends BaseReflectionProperty
{
    use InitializationTrait, InternalPropertiesEmulationTrait;

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
        PropertyProperty $propertyNode = null
    ) {
        $this->className = ltrim($className, '\\');
        if (!$propertyType || !$propertyNode) {
            list ($propertyType, $propertyNode) = ReflectionEngine::parseClassProperty($className, $propertyName);
        }

        $this->propertyTypeNode = $propertyType;
        $this->propertyNode     = $propertyNode;

        // Let's unset original read-only properties to have a control over them via __get
        unset($this->name, $this->class);
    }

    /**
     * Returns an AST-node for property
     *
     * @return PropertyProperty
     */
    public function getNode()
    {
        return $this->propertyNode;
    }

    /**
     * Returns an AST-node for property type
     *
     * @return Property
     */
    public function getTypeNode()
    {
        return $this->propertyTypeNode;
    }

    /**
     * Emulating original behaviour of reflection
     */
    public function ___debugInfo()
    {
        return array(
            'name'  => isset($this->propertyNode) ? $this->propertyNode->name->toString() : 'unknown',
            'class' => $this->className
        );
    }

    /**
     * Return string representation of this little old property.
     *
     * @return string
     */
    public function __toString()
    {
        return sprintf(
            "Property [%s %s $%s ]\n",
            $this->isStatic() ? '' : ($this->isDefault() ? ' <default>' : ' <dynamic>'),
            join(' ', \Reflection::getModifierNames($this->getModifiers())),
            $this->getName()
        );
    }

    /**
     * {@inheritDoc}
     */
    public function getDeclaringClass()
    {
        return new ReflectionClass($this->className);
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
     * @inheritDoc
     */
    public function getName()
    {
        return $this->propertyNode->name->toString();
    }

    /**
     * @inheritDoc
     */
    public function getValue($object = null)
    {
        if (!isset($object)) {
            $solver = new NodeExpressionResolver($this->getDeclaringClass());
            if (!isset($this->propertyNode->default)) {
                return null;
            }
            $solver->process($this->propertyNode->default);

            return $solver->getValue();
        }

        $this->initializeInternalReflection();

        return parent::getValue($object);
    }

    /**
     * @inheritDoc
     */
    public function isDefault()
    {
        // TRUE if the property was declared at compile-time

        return true;
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
    public function isPublic()
    {
        return $this->propertyTypeNode->isPublic();
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
    public function setAccessible($accessible)
    {
        $this->initializeInternalReflection();

        parent::setAccessible($accessible);
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
     * Parses properties from the concrete class node
     *
     * @param ClassLike $classLikeNode Class-like node
     * @param string    $fullClassName FQN of the class
     *
     * @return array|ReflectionProperty[]
     */
    public static function collectFromClassNode(ClassLike $classLikeNode, $fullClassName)
    {
        $properties = [];

        foreach ($classLikeNode->stmts as $classLevelNode) {
            if ($classLevelNode instanceof Property) {
                foreach ($classLevelNode->props as $classPropertyNode) {
                    $propertyName = $classPropertyNode->name->toString();
                    $properties[$propertyName] = new static(
                        $fullClassName,
                        $propertyName,
                        $classLevelNode,
                        $classPropertyNode
                    );
                }
            }
        }

        return $properties;
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
