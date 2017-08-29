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
use ReflectionClass as BaseReflectionClass;

/**
 * AST-based reflection for class property
 */
class ReflectionProperty extends BaseReflectionProperty implements IReflection
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
     * Name of the property
     *
     * @var string
     */
    private $propertyName;

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
        $this->className    = $className;
        $this->propertyName = $propertyName;
        if (!$propertyType || !$propertyNode) {
            $propertyType = null;
            $propertyNode = null;
            $isUserDefined = true;
            // If either node is non-null, it must be user-defined.
            if (!($propertyType || $propertyNode) && $this->wasIncluded()) {
                $nativeRef = new BaseReflectionClass($this->className);
                $isUserDefined = $nativeRef->isUserDefined();
            }
            if ($isUserDefined) {
                list ($propertyType, $propertyNode) =
                    ReflectionEngine::parseClassProperty($className, $propertyName);
                if (!($classLevelNode instanceof Property) || !is_object($classProperty)) {
                    throw new \Exception('Internal error: both $classLevelNode and $classProperty should be populated: ' . var_export(['$classLevelNode' => $classLevelNode, '$classProperty' => $classProperty], true));
                }
                if (!isset($propertyNode)) {
                    $this->propertyName = NULL;
                }
                $this->propertyTypeNode = $propertyType;
                $this->propertyNode     = $propertyNode;
            }
        }
        if ($this->propertyNode) {
            $this->propertyName = $this->propertyNode->name;
        }

        // Let's unset original read-only properties to have a control over them via __get
        unset($this->name, $this->class);
    }

    /**
     * Emulating original behaviour of reflection
     */
    public function ___debugInfo()
    {
        return array(
            'name'  => isset($this->propertyName) ? $this->propertyName : 'unknown',
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
        if (!$this->propertyNode) {
            $this->initializeInternalReflection();
            return parent::getDocComment();
        }
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
        return $this->propertyName;
    }

    /**
     * @inheritDoc
     */
    public function getValue($object = null)
    {
        if (!$this->propertyNode) {
            $this->initializeInternalReflection();
            error_log('getValue: '. var_export(['$this->propertyNode' => $this->propertyNode], true));
            return parent::getValue($object);
        }
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
        if (!$this->propertyNode) {
            $this->initializeInternalReflection();
            return parent::isDefault();
        }
        // TRUE if the property was declared at compile-time

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function isPrivate()
    {
        if (!$this->propertyNode) {
            $this->initializeInternalReflection();
            return parent::isPrivate();
        }
        return $this->propertyTypeNode->isPrivate();
    }

    /**
     * {@inheritDoc}
     */
    public function isProtected()
    {
        if (!$this->propertyNode) {
            $this->initializeInternalReflection();
            return parent::isProtected();
        }
        return $this->propertyTypeNode->isProtected();
    }

    /**
     * {@inheritDoc}
     */
    public function isPublic()
    {
        if (!$this->propertyNode) {
            $this->initializeInternalReflection();
            return parent::isPublic();
        }
        return $this->propertyTypeNode->isPublic();
    }

    /**
     * {@inheritDoc}
     */
    public function isStatic()
    {
        if (!$this->propertyNode) {
            $this->initializeInternalReflection();
            return parent::isStatic();
        }
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
                    $propertyName = $classPropertyNode->name;
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

    /**
     * Has class been loaded by PHP.
     *
     * @return bool
     *     If class file was included.
     */
    public function wasIncluded()
    {
        return
            interface_exists($this->className, false) ||
            trait_exists(    $this->className, false) ||
            class_exists(    $this->className, false);
    }
}
