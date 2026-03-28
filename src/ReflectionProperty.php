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
use Go\ParserReflection\Resolver\NodeExpressionResolver;
use Go\ParserReflection\Resolver\TypeExpressionResolver;
use PhpParser\Node\Identifier;
use PhpParser\Node\Param;
use PhpParser\Node\PropertyItem;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Enum_;
use PhpParser\Node\Stmt\Property;
use Reflection;
use ReflectionProperty as BaseReflectionProperty;

/**
 * AST-based reflection for class property
 * @see \Go\ParserReflection\ReflectionPropertyTest
 */
final class ReflectionProperty extends BaseReflectionProperty
{
    use InitializationTrait;
    use InternalPropertiesEmulationTrait;
    use AttributeResolverTrait;

    /**
     * Re-declare to remove PHP 8.4 { get; } hooks so these properties can be unset in constructor
     */
    public string $name;
    public string $class;

    private Property|Param $propertyOrPromotedParam;

    private PropertyItem|Param $propertyItemOrPromotedParam;

    /**
     * Name of the class
     */
    private string $className;

    private \ReflectionUnionType|\ReflectionNamedType|\ReflectionIntersectionType|null $type = null;

    private mixed $defaultValue = null;

    private bool $isDefaultValueConstant = false;

    private ?string $defaultValueConstantName;

    private ?string $defaultValueConstExpr;

    /**
     * Initializes a reflection for the property
     *
     * @param Property|Param|null $propertyOrPromotedParam Property type definition node
     * @param PropertyItem|Param|null $propertyItemOrPromotedParam Concrete property definition (value, name)
     * @throws ReflectionException
     */
    public function __construct(
        string             $className,
        string             $propertyName,
        Property|Param|null     $propertyOrPromotedParam = null,
        PropertyItem|Param|null $propertyItemOrPromotedParam = null
    ) {
        $this->className = ltrim($className, '\\');
        if (!$propertyOrPromotedParam || !$propertyItemOrPromotedParam) {
            [$propertyOrPromotedParam, $propertyItemOrPromotedParam] = ReflectionEngine::parseClassProperty($className, $propertyName);
        }

        $this->propertyOrPromotedParam     = $propertyOrPromotedParam;
        $this->propertyItemOrPromotedParam = $propertyItemOrPromotedParam;

        // Both PropertyItem and Param has `default` property
        if (isset($this->propertyItemOrPromotedParam->default) && $this->hasDefaultValue()) {
            $expressionSolver = new NodeExpressionResolver($this->getDeclaringClass());
            $expressionSolver->process($this->propertyItemOrPromotedParam->default);

            $this->defaultValue             = $expressionSolver->getValue();
            $this->isDefaultValueConstant   = $expressionSolver->isConstant();
            $this->defaultValueConstantName = $expressionSolver->getConstantName();
            $this->defaultValueConstExpr    = $expressionSolver->getConstExpression();
        }

        if ($this->hasType()) {
            // If we have null value, this handled internally as nullable type too
            $hasDefaultNull = $this->hasDefaultValue() && $this->getDefaultValue() === null;

            $typeResolver = new TypeExpressionResolver();
            $typeResolver->process($this->propertyOrPromotedParam->type, $hasDefaultNull);

            $this->type = $typeResolver->getType();
        }

        // Let's unset original read-only properties to have a control over them via __get
        unset($this->name, $this->class);
    }

    /**
     * Returns an AST-node for property item
     */
    public function getNode(): PropertyItem|Param
    {
        return $this->propertyItemOrPromotedParam;
    }

    /**
     * Returns an AST-node for property type
     */
    public function getTypeNode(): Property|Param
    {
        return $this->propertyOrPromotedParam;
    }

    /**
     * Emulating original behaviour of reflection
     */
    public function __debugInfo(): array
    {
        return [
            'name'  => $this->getName(),
            'class' => $this->className
        ];
    }

    /**
     * Return string representation of this little old property.
     */
    public function __toString(): string
    {
        $propertyType    = $this->getType();
        $hasDefaultValue = $this->hasDefaultValue();
        $defaultValue    = '';
        if ($hasDefaultValue) {
            // For constant fetch expressions, PHP renders now expression
            if ($this->isDefaultValueConstant) {
                $defaultValue = $this->defaultValueConstantName;
            } elseif (is_array($this->getDefaultValue())) {
                $defaultValue = $this->defaultValueConstExpr;
            } else {
                $defaultValue = var_export($this->getDefaultValue(), true);
            }
        }

        return sprintf(
            "Property [ %s %s$%s%s ]\n",
            implode(' ', Reflection::getModifierNames($this->getModifiers())),
            $propertyType ? ReflectionType::convertToDisplayType($propertyType) . ' ' : '',
            $this->getName(),
            $hasDefaultValue ? (' = ' . $defaultValue) : ''
        );
    }

    /**
     * {@inheritDoc}
     *
     * @return \ReflectionClass<object>
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
        $docBlock = $this->propertyOrPromotedParam->getDocComment();

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
        if (PHP_VERSION_ID >= 80400 && $this->isAbstract()) {
            $modifiers += self::IS_ABSTRACT;
        }
        if (PHP_VERSION_ID >= 80400 && $this->isFinal()) {
            $modifiers += self::IS_FINAL;
        }
        if (PHP_VERSION_ID >= 80400 && $this->isProtectedSet()) {
            $modifiers += self::IS_PROTECTED_SET;
        }
        if (PHP_VERSION_ID >= 80400 && $this->isPrivateSet()) {
            $modifiers += self::IS_PRIVATE_SET;
        }

        // Handle PHP 8.4+ asymmetric visibility modifiers
        // Note: IS_PRIVATE_SET and IS_PROTECTED_SET are only added for properties with explicit
        // asymmetric visibility syntax like "public private(set) $prop", not for regular readonly properties
        // TODO: Implement when nikic/php-parser supports asymmetric visibility syntax

        return $modifiers;
    }

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        $node = $this->propertyItemOrPromotedParam;

        return match (true) {
            $node instanceof PropertyItem => $node->name->toString(),
            default => (string) $node->var->name,
        };
    }

    /**
     * @inheritDoc
     */
    public function getValue(object|null $object = null): mixed
    {
        if (!isset($object)) {
            return $this->getDefaultValue();
        }

        // With object we should call original reflection to determine property value
        $this->initializeInternalReflection();

        return parent::getValue($object);
    }

    /**
     * @inheritDoc
     */
    public function getType(): \ReflectionNamedType|\ReflectionUnionType|\ReflectionIntersectionType|null
    {
        return $this->type;
    }

    /**
     * @inheritDoc
     *
     * @see Property::$type
     * @see Param::$type
     */
    public function hasType(): bool
    {
        return isset($this->propertyOrPromotedParam->type);
    }

    /**
     * @inheritDoc
     *
     * @see PropertyItem::$default
     * @see Param::$default
     *
     * @see https://bugs.php.net/bug.php?id=81386 For corner-case with promoted properties and default values
     */
    public function hasDefaultValue(): bool
    {
        return (isset($this->propertyItemOrPromotedParam->default) && !$this->isPromoted()) || !$this->hasType();
    }

    /**
     * @inheritDoc
     */
    public function getDefaultValue(): mixed
    {
        return $this->defaultValue;
    }

    /**
     * @inheritDoc
     */
    public function isAbstract(): bool
    {
        if ($this->propertyOrPromotedParam instanceof Property) {
            return $this->propertyOrPromotedParam->isAbstract();
        }

        return false;
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
     *
     * @see Property::isFinal()
     */
    public function isFinal(): bool
    {
        $explicitFinal = false;
        if ($this->propertyOrPromotedParam instanceof Property) {
            $explicitFinal = $this->propertyOrPromotedParam->isFinal();
        }

        // Property with private(set) modifier is implicitly final
        return $explicitFinal || $this->isPrivateSet();
    }

    /**
     * {@inheritDoc}
     *
     * @see Property::isPrivate()
     * @see Param::isPrivate()
     */
    public function isPrivate(): bool
    {
        return $this->propertyOrPromotedParam->isPrivate();
    }

    /**
     * @inheritDoc
     *
     * @see Property::isPrivateSet()
     * @see Param::isPrivateSet()
     */
    public function isPrivateSet(): bool
    {
        return ($this->propertyOrPromotedParam->isPrivateSet() && !$this->propertyOrPromotedParam->isPrivate());
    }

    /**
     * {@inheritDoc}
     *
     * @see Property::isProtected()
     * @see Param::isProtected()
     */
    public function isProtected(): bool
    {
        return $this->propertyOrPromotedParam->isProtected();
    }

    /**
     * @inheritDoc
     *
     * @see Property::isProtectedSet()
     * @see Param::isProtectedSet()
     */
    public function isProtectedSet(): bool
    {
        /*
         * Behavior of readonly is to imply protected(set), not private(set).
         * A readonly property may still be explicitly declared private(set), in which case it will also be implicitly final
         */
        return ($this->propertyOrPromotedParam->isProtectedSet() && !$this->propertyOrPromotedParam->isProtected())
            || ($this->isPublic() && $this->isReadonly() && !$this->isPrivateSet() && !$this->propertyOrPromotedParam->isPublicSet());
    }

    /**
     * {@inheritDoc}
     *
     * @see Property::isPublic()
     * @see Param::isPublic()
     */
    public function isPublic(): bool
    {
        return $this->propertyOrPromotedParam->isPublic();
    }

    /**
     * {@inheritDoc}
     *
     * @see Property::isStatic()
     */
    public function isStatic(): bool
    {
        // All promoted properties are dynamic and not static
        return !$this->isPromoted() && $this->propertyOrPromotedParam->isStatic();
    }

    /**
     * {@inheritDoc}
     *
     * @see Param::isPromoted()
     */
    public function isPromoted(): bool
    {
        return $this->propertyOrPromotedParam instanceof Param;
    }

    /**
     * {@inheritDoc}
     *
     * @see Property::isReadonly()
     * @see Param::isReadonly()
     */
    public function isReadOnly(): bool
    {
        return $this->propertyOrPromotedParam->isReadonly() || $this->getDeclaringClass()->isReadOnly();
    }

    /**
     * {@inheritDoc}
     */
    public function isInitialized(?object $object = null): bool
    {
        // If we have already object, we should proceed to original implementation
        if (isset($object)) {
            $this->initializeInternalReflection();

            return parent::isInitialized($object);
        }

        // For static properties, we could check if we have default value
        return $this->hasDefaultValue();
    }

    /**
     * @inheritDoc
     */
    public function isVirtual(): bool
    {
        if (!$this->propertyOrPromotedParam instanceof Property) {
            return false;
        }
        $hooks = $this->propertyOrPromotedParam->hooks;
        if (empty($hooks)) {
            return false;
        }
        // A property is virtual if it has hooks but none expose backing storage (byRef)
        foreach ($hooks as $hook) {
            if ($hook->byRef) {
                return false;
            }
        }

        return true;
    }

    /**
     * {@inheritDoc}
     */
    #[\Deprecated('This method is no-op starting from PHP 8.1', since: '8.1')]
    public function setAccessible(bool $accessible): void
    {
    }

    /**
     * @inheritDoc
     */
    public function setValue(mixed $objectOrValue, mixed $value = null): void
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
     * @return ReflectionProperty[]
     */
    public static function collectFromClassNode(ClassLike $classLikeNode, string $fullClassName): array
    {
        $properties = [];

        foreach ($classLikeNode->stmts as $classLevelNode) {

            // Old-fashioned properties
            if ($classLevelNode instanceof Property) {
                foreach ($classLevelNode->props as $classPropertyNode) {
                    $propertyName = $classPropertyNode->name->toString();
                    $properties[$propertyName] = new self(
                        $fullClassName,
                        $propertyName,
                        $classLevelNode,
                        $classPropertyNode
                    );
                }
            }

            // We might also have promoted properties inside constructor
            if ($classLevelNode instanceof ClassMethod && $classLevelNode->name->toString() === '__construct') {
                foreach ($classLevelNode->getParams() as $paramNode) {
                    if ($paramNode->isPromoted()) {
                        $propertyName = (string) $paramNode->var->name;
                        $properties[$propertyName] = new self(
                            $fullClassName,
                            $propertyName,
                            $paramNode,
                            $paramNode
                        );
                    }
                 }
            }
        }

        // Enum has special `name` (and `value` for Backed Enums) properties
        if ($classLikeNode instanceof Enum_) {
            $properties['name'] = self::createEnumNameProperty($fullClassName);
            if (isset($classLikeNode->scalarType)) {
                $valueProperty = self::createEnumValueProperty($classLikeNode, $fullClassName);
                $properties['value'] = $valueProperty;
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

    private static function createEnumNameProperty(string $fullClassName): ReflectionProperty
    {
        $namePropertyNode = (new \PhpParser\Builder\Property('name'))
            ->makeReadonly()
            ->makePublic()
            ->setType('string')
            ->getNode();

        return new self(
            $fullClassName,
            'name',
            $namePropertyNode,
            $namePropertyNode->props[0]
        );
    }

    private static function createEnumValueProperty(Enum_ $classLikeNode, string $fullClassName): ReflectionProperty
    {
        $valuePropertyNode = (new \PhpParser\Builder\Property('value'))
            ->makeReadonly()
            ->makePublic()
            ->setType($classLikeNode->scalarType)
            ->getNode();

        return new self(
            $fullClassName,
            'value',
            $valuePropertyNode,
            $valuePropertyNode->props[0]
        );
    }
}
