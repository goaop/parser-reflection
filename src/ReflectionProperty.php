<?php
declare(strict_types=1);
/**
 * Parser Reflection API
 *
 * @copyright Copyright 2015, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\ParserReflection;

use Go\ParserReflection\Traits\AttributeResolverTrait;
use Go\ParserReflection\Traits\InitializationTrait;
use Go\ParserReflection\Traits\InternalPropertiesEmulationTrait;
use Go\ParserReflection\ValueResolver\NodeExpressionResolver;
use JetBrains\PhpStorm\Deprecated;
use PhpParser\Node\PropertyItem;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Property;
use Reflection;
use ReflectionProperty as BaseReflectionProperty;

/**
 * AST-based reflection for class property
 * @see \Go\ParserReflection\ReflectionPropertyTest
 */
class ReflectionProperty extends BaseReflectionProperty
{
    use InitializationTrait;
    use InternalPropertiesEmulationTrait;
    use AttributeResolverTrait;

    private Property $propertyNode;

    private PropertyItem $propertyItem;

    /**
     * Name of the class
     */
    private string $className;

    /**
     * Initializes a reflection for the property
     *
     * @param string            $className    Name of the class with properties
     * @param string            $propertyName Name of the property to reflect
     * @param ?Property         $propertyType Property type definition node
     * @param ?PropertyItem     $propertyNode Concrete property definition (value, name)
     */
    public function __construct(
        $className,
        string $propertyName,
        Property $propertyType = null,
        PropertyItem $propertyNode = null
    ) {
        $this->className = ltrim($className, '\\');
        if (!$propertyType || !$propertyNode) {
            [$propertyType, $propertyNode] = ReflectionEngine::parseClassProperty($className, $propertyName);
        }

        $this->propertyNode = $propertyType;
        $this->propertyItem = $propertyNode;

        // Let's unset original read-only properties to have a control over them via __get
        unset($this->name, $this->class);
    }

    /**
     * Returns an AST-node for property item
     */
    public function getNode(): PropertyItem
    {
        return $this->propertyItem;
    }

    /**
     * Returns an AST-node for property type
     */
    public function getTypeNode(): Property
    {
        return $this->propertyNode;
    }

    /**
     * Emulating original behaviour of reflection
     */
    public function __debugInfo(): array
    {
        return [
            'name'  => isset($this->propertyItem) ? $this->propertyItem->name->toString() : 'unknown',
            'class' => $this->className
        ];
    }

    /**
     * Return string representation of this little old property.
     */
    public function __toString(): string
    {
        $defaultValueDisplay = '';

        if ($this->isDefault()) {
            $this->__initialize();
            $defaultValue = $this->getDefaultValue();

            $originalDisplay = parent::__toString();

            // @see https://3v4l.org/7q1LT
            $list = explode(' = ', $originalDisplay);
            array_shift($list);

            $defaultValueFromDisplay = implode(' = ', $list);

            $defaultValueDisplay = '= ' . rtrim($defaultValueFromDisplay, ' ]' . PHP_EOL);

            if (is_array($defaultValue) && str_starts_with($defaultValueDisplay, '= [')) {
                $defaultValueDisplay .= ']';
            }
        }

        return sprintf(
            "Property [ %s $%s %s ]\n",
            implode(' ', Reflection::getModifierNames($this->getModifiers())),
            $this->getName(),
            $defaultValueDisplay
        );
    }

    /**
     * {@inheritDoc}
     */
    public function getDeclaringClass(): \ReflectionClass
    {
        return new ReflectionClass($this->className);
    }

    /**
     * @inheritDoc
     */
    public function getDocComment(): string|false
    {
        $docBlock = $this->propertyNode->getDocComment();

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
        if ($this->isReadOnly()) {
            $modifiers += self::IS_READONLY;
        }

        return $modifiers;
    }

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return $this->propertyItem->name->toString();
    }

    /**
     * @inheritDoc
     */
    public function getValue(object|null $object = null): mixed
    {
        if (!isset($object)) {
            $solver = new NodeExpressionResolver($this->getDeclaringClass());
            if (!isset($this->propertyItem->default)) {
                return null;
            }
            $solver->process($this->propertyItem->default);

            return $solver->getValue();
        }

        $this->initializeInternalReflection();

        return parent::getValue($object);
    }

    /**
     * @inheritDoc
     */
    public function isDefault(): bool
    {
        // TRUE if the property was declared at compile-time

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function isPrivate(): bool
    {
        return $this->propertyNode->isPrivate();
    }

    /**
     * {@inheritDoc}
     */
    public function isProtected(): bool
    {
        return $this->propertyNode->isProtected();
    }

    /**
     * {@inheritDoc}
     */
    public function isPublic(): bool
    {
        return $this->propertyNode->isPublic();
    }

    /**
     * {@inheritDoc}
     */
    public function isStatic(): bool
    {
        return $this->propertyNode->isStatic();
    }

    /**
     * {@inheritDoc}
     */
    public function isReadOnly(): bool
    {
        return $this->propertyNode->isReadonly() || $this->getDeclaringClass()->isReadOnly();
    }

    /**
     * {@inheritDoc}
     */
    #[Deprecated(reason: 'This method is no-op starting from PHP 8.1', since: '8.1')]
    public function setAccessible(bool $accessible): void
    {
    }

    /**
     * @inheritDoc
     */
    public function setValue($object, $value = null): void
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
     */
    protected function __initialize(): void
    {
        parent::__construct($this->className, $this->getName());
    }
}
