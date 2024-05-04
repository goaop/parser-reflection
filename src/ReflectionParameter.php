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
use Go\ParserReflection\Traits\InternalPropertiesEmulationTrait;
use Go\ParserReflection\Resolver\NodeExpressionResolver;
use Go\ParserReflection\Resolver\TypeExpressionResolver;
use JetBrains\PhpStorm\Deprecated;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\BinaryOp\Concat;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\Param;
use PhpParser\PrettyPrinter\Standard;
use ReflectionFunctionAbstract;
use ReflectionParameter as BaseReflectionParameter;

/**
 * AST-based reflection for method/function parameter
 * @see \Go\ParserReflection\ReflectionParameterTest
 */
class ReflectionParameter extends BaseReflectionParameter
{
    use InternalPropertiesEmulationTrait;
    use AttributeResolverTrait;

    /**
     * Reflection function or method
     */
    private ReflectionFunctionAbstract $declaringFunction;

    /**
     * Stores the default value for node (if present)
     */
    private mixed $defaultValue;

    /**
     * Whether or not default value is constant
     */
    private bool $isDefaultValueConstant = false;

    /**
     * Name of the constant of default value
     *
     * @see $isDefaultValueConstant
     */
    private ?string $defaultValueConstantName;

    /**
     * Index of parameter in the list
     */
    private int $parameterIndex;

    /**
     * Concrete parameter node
     */
    private Param $parameterNode;

    private bool $isDefaultValueConstExpr;

    private ?string $defaultValueConstExpr;

    private \ReflectionUnionType|\ReflectionNamedType|\ReflectionIntersectionType|null $type = null;

    /**
     * Initializes a reflection for the property
     */
    public function __construct(
        string|array $unusedFunctionName,
        string $parameterName,
        Param $parameterNode,
        int $parameterIndex,
        ReflectionFunctionAbstract $declaringFunction
    ) {
        // Let's unset original read-only property to have a control over it via __get
        unset($this->name);

        $this->parameterNode     = $parameterNode;
        $this->parameterIndex    = $parameterIndex;
        $this->declaringFunction = $declaringFunction;

        if ($declaringFunction instanceof \ReflectionMethod) {
            $context = $declaringFunction->getDeclaringClass();
        } else {
            $context = $declaringFunction;
        }

        if ($this->isDefaultValueAvailable()) {
            $expressionSolver = new NodeExpressionResolver($context);
            $expressionSolver->process($this->parameterNode->default);

            $this->defaultValue             = $expressionSolver->getValue();
            $this->isDefaultValueConstant   = $expressionSolver->isConstant();
            $this->defaultValueConstantName = $expressionSolver->getConstantName();
            $this->isDefaultValueConstExpr  = $expressionSolver->isConstExpression();
            $this->defaultValueConstExpr    = $expressionSolver->getConstExpression();
        }

        if ($this->hasType()) {
            // If we have null value, this handled internally as nullable type too
            $hasDefaultNull = $this->isDefaultValueAvailable() && $this->getDefaultValue() === null;

            $typeResolver = new TypeExpressionResolver($this->getDeclaringClass());
            $typeResolver->process($this->parameterNode->type, $hasDefaultNull);

            $this->type = $typeResolver->getType();
        }
    }

    /**
     * Returns an AST-node for parameter
     */
    public function getNode(): Param
    {
        return $this->parameterNode;
    }

    /**
     * Emulating original behaviour of reflection
     */
    public function __debugInfo(): array
    {
        return [
            'name' => (string)$this->parameterNode->var->name,
        ];
    }

    /**
     * Returns string representation of this parameter.
     */
    public function __toString(): string
    {
        $parameterType   = $this->getType();
        $isOptional      = $this->isOptional();
        $hasDefaultValue = $this->isDefaultValueAvailable();
        $defaultValue    = '';
        if ($hasDefaultValue) {
            // For constant fetch expressions, PHP renders now expression
            if ($this->isDefaultValueConstExpr) {
                $defaultValue = $this->defaultValueConstExpr;
            } elseif ($this->isDefaultValueConstant){
                $defaultValue = $this->defaultValueConstantName;
            } else {
                $defaultValue = var_export($this->getDefaultValue(), true);
            }
        }

        return sprintf(
            'Parameter #%d [ %s %s%s%s$%s%s ]',
            $this->parameterIndex,
            $isOptional ? '<optional>' : '<required>',
            $parameterType ? ReflectionType::convertToDisplayType($parameterType) . ' ' : '',
            $this->isVariadic() ? '...' : '',
            $this->isPassedByReference() ? '&' : '',
            $this->getName(),
            ($isOptional && $hasDefaultValue) ? (' = ' . $defaultValue) : ''
        );
    }

    /**
     * {@inheritDoc}
     */
    public function allowsNull(): bool
    {
        // All non-typed parameters allows null by default
        if (!$this->hasType()) {
            return true;
        }

        return $this->type->allowsNull();
    }

    /**
     * {@inheritDoc}
     */
    public function canBePassedByValue(): bool
    {
        return !$this->isPassedByReference();
    }

    /**
     * @inheritDoc
     */
    #[Deprecated(reason: "Use ReflectionParameter::getType() and the ReflectionType APIs should be used instead.", since: "8.0")]
    public function getClass(): ?\ReflectionClass
    {
        $parameterType = $this->parameterNode->type;
        if ($parameterType instanceof Identifier && $parameterType->name === 'iterable') {
            // This is how PHP represents iterable pseudo-class
            $parameterType = new Name\FullyQualified(\Traversable::class);
        }
        if ($parameterType instanceof Name) {
            // If we have resolved type name, we should use it instead
            if ($parameterType->hasAttribute('resolvedName')) {
                $parameterType = $parameterType->getAttribute('resolvedName');
            }

            if (!$parameterType instanceof Name\FullyQualified) {
                $parameterTypeName = $parameterType->toString();

                if ('self' === $parameterTypeName) {
                    return $this->getDeclaringClass();
                }

                if ('parent' === $parameterTypeName) {
                    return $this->getDeclaringClass()->getParentClass();
                }

                throw new ReflectionException("Can not resolve a class name for parameter");
            }
            $className = $parameterType->toString();

            $classOrInterfaceExists = class_exists($className, false) || interface_exists($className, false);

            return $classOrInterfaceExists ? new \ReflectionClass($className) : new ReflectionClass($className);
        }

        return null;
    }

    /**
     * {@inheritDoc}
     */
    public function getDeclaringClass(): ?\ReflectionClass
    {
        if ($this->declaringFunction instanceof \ReflectionMethod) {
            return $this->declaringFunction->getDeclaringClass();
        }

        return null;
    }

    /**
     * {@inheritDoc}
     */
    public function getDeclaringFunction(): ReflectionFunctionAbstract
    {
        return $this->declaringFunction;
    }

    /**
     * {@inheritDoc}
     */
    public function getDefaultValue(): mixed
    {
        if (!$this->isDefaultValueAvailable()) {
            throw new ReflectionException('Internal error: Failed to retrieve the default value');
        }

        return $this->defaultValue;
    }

    /**
     * {@inheritDoc}
     */
    public function getDefaultValueConstantName(): null|string
    {
        if (!$this->isDefaultValueAvailable()) {
            throw new ReflectionException('Internal error: Failed to retrieve the default value');
        }

        return $this->defaultValueConstantName;
    }

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return (string)$this->parameterNode->var->name;
    }

    /**
     * {@inheritDoc}
     */
    public function getPosition(): int
    {
        return $this->parameterIndex;
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
     */
    public function hasType(): bool
    {
        return isset($this->parameterNode->type);
    }

    /**
     * @inheritDoc
     */
    #[Deprecated(reason: "Use ReflectionParameter::getType() instead.", since: "8.0")]
    public function isArray(): bool
    {
        $type = $this->parameterNode->type;

        return ($type instanceof Identifier) && 'array' === $type->name;
    }

    /**
     * @inheritDoc
     */
    #[Deprecated(reason: "Use ReflectionParameter::getType() instead.", since: "8.0")]
    public function isCallable(): bool
    {
        $type = $this->parameterNode->type;

        return ($type instanceof Identifier) && 'callable' === $type->name;
    }

    /**
     * @inheritDoc
     */
    public function isDefaultValueAvailable(): bool
    {
        return isset($this->parameterNode->default) && $this->allSiblingsAreOptional();
    }

    /**
     * {@inheritDoc}
     */
    public function isDefaultValueConstant(): bool
    {
        return $this->isDefaultValueConstant;
    }

    /**
     * {@inheritDoc}
     */
    public function isOptional(): bool
    {
        return $this->isVariadic() || $this->isDefaultValueAvailable();
    }

    /**
     * @inheritDoc
     */
    public function isPassedByReference(): bool
    {
        return $this->parameterNode->byRef;
    }

    /**
     * @inheritDoc
     */
    public function isPromoted(): bool
    {
        return $this->parameterNode->isPromoted();
    }

    /**
     * @inheritDoc
     */
    public function isVariadic(): bool
    {
        return $this->parameterNode->variadic;
    }

    /**
     * Returns true if all following parameters are optional (either have values or variadic)
     */
    private function allSiblingsAreOptional(): bool
    {
        // start from PHP 8.1, isDefaultValueAvailable() returns false if next parameter is required
        // see https://github.com/php/php-src/issues/8090
        $parameters = $this->declaringFunction->getNode()->getParams();
        for ($nextParamIndex = $this->parameterIndex + 1; $nextParamIndex < count($parameters); ++$nextParamIndex) {
            if (!isset($parameters[$nextParamIndex]->default) && !$parameters[$nextParamIndex]->variadic) {
                return false;
            }
        }

        return true;
    }
}
