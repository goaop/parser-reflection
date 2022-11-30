<?php
/** @noinspection PhpUnusedFieldDefaultValueInspection */
declare(strict_types=1);
/**
 * Parser Reflection API
 *
 * @copyright Copyright 2015-2022, Lisachenko Alexander <lisachenko.it@gmail.com>
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
    use InitializationTrait;
    use InternalPropertiesEmulationTrait;

    /**
     * Type of property node
     *
     * @var Property
     */
    private mixed $propertyTypeNode;

    /**
     * Concrete property node
     *
     * @var PropertyProperty
     */
    private mixed $propertyNode;

    /**
     * Name of the class
     *
     * @var string
     */
    private string $className = '';

    /**
     * Initializes a reflection for the property
     *
     * @param string            $className    Name of the class with properties
     * @param string            $propertyName Name of the property to reflect
     * @param ?Property         $propertyType Property type definition node
     * @param ?PropertyProperty $propertyNode Concrete property definition (value, name)
     *
     * @noinspection PhpMissingParentConstructorInspection*/
    public function __construct(
        string $className,
        string $propertyName,
        Property $propertyType = null,
        PropertyProperty $propertyNode = null
    ) {
        $this->className = ltrim($className, '\\');
        if (!$propertyType || !$propertyNode) {
            [$propertyType, $propertyNode] = ReflectionEngine::parseClassProperty($className, $propertyName);
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
    public function getNode(): PropertyProperty
    {
        return $this->propertyNode;
    }

    /**
     * Returns an AST-node for property type
     *
     * @return Property
     */
    public function getTypeNode(): Property
    {
        return $this->propertyTypeNode;
    }

    /**
     * Emulating original behaviour of reflection.
     *
     * Called when invoking {@link var_dump()} on an object
     *
     * @return array{name: string, class: class-string}
     */
    public function __debugInfo(): array
    {
        return [
            'name'  => isset($this->propertyNode) ? $this->propertyNode->name->toString() : 'unknown',
            'class' => $this->className
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function getDeclaringClass(): \ReflectionClass|ReflectionClass
    {
        return new ReflectionClass($this->className);
    }

    /**
     * {@inheritDoc}
     */
    public function getDocComment(): bool|string
    {
        $docBlock = $this->propertyTypeNode->getDocComment();

        return $docBlock ? $docBlock->getText() : false;
    }

    /**
     * {@inheritDoc}
     */
    public function getModifiers(): int
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
    public function getName(): string
    {
        return $this->propertyNode->name->toString();
    }

    /**
     * {@inheritDoc}
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
     * {@inheritDoc}
     */
    public function isDefault(): bool
    {
        $this->initializeInternalReflection();

        return parent::isDefault();
    }

    /**
     * {@inheritDoc}
     */
    public function getDefaultValue(): mixed
    {
        $this->initializeInternalReflection();

        return parent::getDefaultValue();
    }

    /**
     * {@inheritDoc}
     */
    public function isPrivate(): bool
    {
        return $this->propertyTypeNode->isPrivate();
    }

    /**
     * {@inheritDoc}
     */
    public function isProtected(): bool
    {
        return $this->propertyTypeNode->isProtected();
    }

    /**
     * {@inheritDoc}
     */
    public function isPublic(): bool
    {
        return $this->propertyTypeNode->isPublic();
    }

    /**
     * {@inheritDoc}
     */
    public function isStatic(): bool
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
     * {@inheritDoc}
     */
    public function setValue(mixed $objectOrValue, mixed $value = null)
    {
        $this->initializeInternalReflection();

        parent::setValue($objectOrValue, $value);
    }

    /**
     * Parses properties from the concrete class node
     *
     * @param ClassLike $classLikeNode Class-like node
     * @param string    $fullClassName FQN of the class
     *
     * @return array|ReflectionProperty[]
     */
    public static function collectFromClassNode(ClassLike $classLikeNode, string $fullClassName): array
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
     *
     * @noinspection PhpDocMissingThrowsInspection
     */
    protected function __initialize(): void
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        parent::__construct($this->className, $this->getName());
    }
}
