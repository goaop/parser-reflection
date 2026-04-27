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
use PhpParser\Modifiers;
use PhpParser\Node\Expr;
use PhpParser\Node\Identifier;
use PhpParser\Node\Param;
use PhpParser\Node\PropertyHook;
use PhpParser\Node\PropertyItem;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Enum_;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\Stmt\Return_;
use PropertyHookType;
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
     *
     * @var class-string<object>
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
     * @param class-string<object> $className
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

        if ($this->hasType() && $this->propertyOrPromotedParam->type !== null) {
            // If we have null value, this handled internally as nullable type too
            $hasDefaultNull = $this->hasDefaultValue() && $this->getDefaultValue() === null;

            $declaringClass  = $this->getDeclaringClass();
            $parentClass     = $declaringClass->getParentClass();
            $parentClassName = ($parentClass !== false) ? $parentClass->getName() : null;

            $typeResolver = new TypeExpressionResolver($this->className, $parentClassName);
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
     * Returns the AST node that contains attribute groups for this property.
     */
    protected function getNodeForAttributes(): Property|Param
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
        if ($this->isAbstract()) {
            $modifiers += self::IS_ABSTRACT;
        }
        if ($this->isFinal()) {
            $modifiers += self::IS_FINAL;
        }
        if ($this->isProtectedSet()) {
            $modifiers += self::IS_PROTECTED_SET;
        }
        if ($this->isPrivateSet()) {
            $modifiers += self::IS_PRIVATE_SET;
        }
        if ($this->isVirtual()) {
            $modifiers += self::IS_VIRTUAL;
        }

        return $modifiers;
    }

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        $node = $this->propertyItemOrPromotedParam;

        if ($node instanceof PropertyItem) {
            return $node->name->toString();
        }
        // $node is Param; var is Expr\Variable|Expr\Error; Expr\Variable->name is string|Expr
        $varName = $node->var instanceof Expr\Variable ? $node->var->name : '';

        return is_string($varName) ? $varName : '';
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
     * {@inheritDoc}
     */
    public function getSettableType(): \ReflectionNamedType|\ReflectionUnionType|\ReflectionIntersectionType|null
    {
        // Virtual properties (get-only hook, no backing store) return 'never'
        if ($this->isVirtual() && !$this->hasHook(PropertyHookType::Set)) {
            $typeResolver = new TypeExpressionResolver($this->className, null);
            $typeResolver->process(new Identifier('never'), false);

            return $typeResolver->getType();
        }

        // If there's a set hook with an explicit typed parameter, resolve that type
        if ($this->propertyOrPromotedParam instanceof Property) {
            foreach ($this->propertyOrPromotedParam->hooks as $hook) {
                if ($hook->name->toLowerString() === 'set' && !empty($hook->params) && $hook->params[0]->type !== null) {
                    $declaringClass  = $this->getDeclaringClass();
                    $parentClass     = $declaringClass->getParentClass();
                    $parentClassName = ($parentClass !== false) ? $parentClass->getName() : null;

                    $typeResolver = new TypeExpressionResolver($this->className, $parentClassName);
                    $typeResolver->process($hook->params[0]->type, false);

                    return $typeResolver->getType();
                }
            }
        }

        return $this->getType();
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
     * {@inheritDoc}
     */
    public function hasHooks(): bool
    {
        return !empty($this->propertyOrPromotedParam->hooks);
    }

    /**
     * {@inheritDoc}
     */
    public function hasHook(PropertyHookType $type): bool
    {
        foreach ($this->propertyOrPromotedParam->hooks as $hook) {
            if ($hook->name->toLowerString() === $type->value) {
                return true;
            }
        }

        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function getHook(PropertyHookType $type): ?ReflectionMethod
    {
        foreach ($this->propertyOrPromotedParam->hooks as $hook) {
            if ($hook->name->toLowerString() === $type->value) {
                return $this->createMethodFromHook($hook, $type);
            }
        }

        return null;
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
            if ($this->propertyOrPromotedParam->isAbstract()) {
                return true;
            }

            // Interface properties with abstract hooks (null body) are implicitly abstract
            foreach ($this->propertyOrPromotedParam->hooks as $hook) {
                if ($hook->body === null) {
                    return true;
                }
            }
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
        return $this->propertyOrPromotedParam instanceof Property && $this->propertyOrPromotedParam->isStatic();
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

        $propertyName = $this->getName();
        foreach ($hooks as $hook) {
            // A short set hook (body is Expr) always stores the result in the backing field
            if ($hook->name->name === 'set' && $hook->body instanceof Expr) {
                return false;
            }
            // A block-form hook that references $this->propertyName uses the backing store
            if (is_array($hook->body) && $this->hookBodyUsesBackingStore($hook->body, $propertyName)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Checks whether the hook body references the property's own backing store via $this->propertyName
     *
     * @param \PhpParser\Node\Stmt[] $stmts
     */
    private function hookBodyUsesBackingStore(array $stmts, string $propertyName): bool
    {
        $finder = new \PhpParser\NodeFinder();
        $found = $finder->findFirst($stmts, function (\PhpParser\Node $node) use ($propertyName): bool {
            return $node instanceof \PhpParser\Node\Expr\PropertyFetch
                && $node->var instanceof \PhpParser\Node\Expr\Variable
                && $node->var->name === 'this'
                && $node->name instanceof \PhpParser\Node\Identifier
                && $node->name->name === $propertyName;
        });

        return $found !== null;
    }

    /**
     * Converts a PropertyHook AST node into a synthetic ClassMethod node and wraps it in a ReflectionMethod.
     */
    private function createMethodFromHook(PropertyHook $hook, PropertyHookType $type): ReflectionMethod
    {
        $propertyName = $this->getName();
        $hookMethodName = '$' . $propertyName . '::' . $type->value;

        // Build the method body (stmts)
        if ($hook->body instanceof Expr) {
            // Short hook: convert expression to return statement (for get) or expression statement (for set)
            if ($type === PropertyHookType::Get) {
                $stmts = [new Return_($hook->body)];
            } else {
                $stmts = [new Expression($hook->body)];
            }
        } elseif (is_array($hook->body)) {
            $stmts = $hook->body;
        } else {
            // Abstract hook (no body)
            $stmts = null;
        }

        // Build parameters
        $params = $hook->params;
        if ($type === PropertyHookType::Set && empty($params)) {
            // Implicit $value parameter with the property's type
            $params = [new Param(
                var: new Expr\Variable('value'),
                type: $this->propertyOrPromotedParam->type,
            )];
        }

        // Build return type
        if ($type === PropertyHookType::Get) {
            $returnType = $this->propertyOrPromotedParam->type;
        } else {
            $returnType = new Identifier('void');
        }

        $classMethodNode = new ClassMethod(
            $hookMethodName,
            [
                'flags'      => Modifiers::PUBLIC,
                'byRef'      => $hook->byRef,
                'params'     => $params,
                'returnType' => $returnType,
                'stmts'      => $stmts,
                'attrGroups' => $hook->attrGroups,
            ],
            $hook->getAttributes()
        );

        return new ReflectionMethod(
            $this->className,
            $hookMethodName,
            $classMethodNode,
            new ReflectionClass($this->className)
        );
    }

    /**
     * {@inheritDoc}
     */
    #[\Deprecated('This method is no-op starting from PHP 8.1', since: '8.1')]
    public function setAccessible(bool $accessible): void
    {
    }

    public function setValue(mixed $objectOrValue, mixed $value = null): void
    {
        $this->initializeInternalReflection();

        if (func_num_args() < 2) {
            // Single-argument form: sets a static property value (PHP 8.4+ canonical form).
            parent::setValue(null, $objectOrValue);

            return;
        }

        if (!$this->isStatic() && $objectOrValue !== null && !is_object($objectOrValue)) {
            throw new \InvalidArgumentException('Expected object or null for $objectOrValue on non-static property');
        }
        // For static properties the object argument must be null; for instance properties it must be an object.
        // After the guard above we know: isStatic() || objectOrValue === null || is_object(objectOrValue).
        $objectArg = is_object($objectOrValue) ? $objectOrValue : null;
        parent::setValue($objectArg, $value);
    }

    /**
     * Parses properties from the concrete class node
     *
     * @param ClassLike            $classLikeNode Class-like node
     * @param class-string<object> $fullClassName FQN of the class
     *
     * @return array<string, ReflectionProperty>
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
                        $varName = $paramNode->var instanceof Expr\Variable ? $paramNode->var->name : '';
                        $propertyName = is_string($varName) ? $varName : '';
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

    /** @param class-string<object> $fullClassName */
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

    /** @param class-string<object> $fullClassName */
    private static function createEnumValueProperty(Enum_ $classLikeNode, string $fullClassName): ReflectionProperty
    {
        $propertyBuilder = (new \PhpParser\Builder\Property('value'))
            ->makeReadonly()
            ->makePublic();
        if ($classLikeNode->scalarType !== null) {
            $propertyBuilder->setType($classLikeNode->scalarType);
        }
        $valuePropertyNode = $propertyBuilder->getNode();

        return new self(
            $fullClassName,
            'value',
            $valuePropertyNode,
            $valuePropertyNode->props[0]
        );
    }
}
