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
use Go\ParserReflection\ValueResolver\NodeExpressionResolver;
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
 */
class ReflectionParameter extends BaseReflectionParameter
{
    use InternalPropertiesEmulationTrait;
    use AttributeResolverTrait;

    /**
     * Reflection function or method
     *
     * @var ReflectionFunctionAbstract
     */
    private $declaringFunction;

    /**
     * Stores the default value for node (if present)
     *
     * @var mixed
     */
    private $defaultValue;

    /**
     * Whether or not default value is constant
     *
     * @var bool
     */
    private $isDefaultValueConstant = false;

    /**
     * Name of the constant of default value
     *
     * @var string
     */
    private $defaultValueConstantName;

    /**
     * Index of parameter in the list
     *
     * @var int
     */
    private $parameterIndex;

    /**
     * Concrete parameter node
     *
     * @var Param
     */
    private $parameterNode;

    /**
     * Initializes a reflection for the property
     *
     * @param string|array                $unusedFunctionName Name of the function/method
     * @param string                      $parameterName      Name of the parameter to reflect
     * @param ?Param                      $parameterNode      Parameter definition node
     * @param int                         $parameterIndex     Index of parameter
     * @param ?ReflectionFunctionAbstract $declaringFunction
     */
    public function __construct(
        $unusedFunctionName,
        $parameterName,
        Param $parameterNode = null,
        $parameterIndex = 0,
        ReflectionFunctionAbstract $declaringFunction = null
    ) {
        // Let's unset original read-only property to have a control over it via __get
        unset($this->name);

        $this->parameterNode     = $parameterNode;
        $this->parameterIndex    = $parameterIndex;
        $this->declaringFunction = $declaringFunction;

        if ($this->isDefaultValueAvailable()) {
            if ($declaringFunction instanceof \ReflectionMethod) {
                $context = $declaringFunction->getDeclaringClass();
            } else {
                $context = $declaringFunction;
            }

            $expressionSolver = new NodeExpressionResolver($context, true);
            $expressionSolver->process($this->parameterNode->default);
            $this->defaultValue             = $expressionSolver->getValue();
            $this->isDefaultValueConstant   = $expressionSolver->isConstant();
            $this->defaultValueConstantName = $expressionSolver->getConstantName();
        }
    }

    /**
     * Returns an AST-node for parameter
     *
     * @return Param
     */
    public function getNode()
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
     *
     * @return string
     */
    public function __toString(): string
    {
        $parameterType   = $this->getType();
        $isOptional      = $this->isOptional();
        $hasDefaultValue = $this->isDefaultValueAvailable();
        $defaultValue    = '';
        if ($hasDefaultValue) {
            $defaultValue = $this->getDefaultValue();

            if ($this->parameterNode->default instanceof Array_) {
                $this->parameterNode->default->setAttribute('kind', Array_::KIND_SHORT);
                $printer = new Standard(['shortArraySyntax' => true]);
                $defaultValue = $printer->prettyPrintExpr($this->parameterNode->default);
            } elseif (($this->parameterNode->default instanceof Concat
                    || $this->parameterNode->default instanceof ClassConstFetch
                    || is_string($defaultValue)
                ) && $this->declaringFunction instanceof \ReflectionMethod
                ) {
                    if ($this->parameterNode->default instanceof ClassConstFetch && is_string($defaultValue) && str_contains($defaultValue, '\\')) {
                        $defaultValue = str_replace('\\', '\\', var_export($defaultValue, true));
                    }
            } else {
                $defaultValue = var_export($defaultValue, true);
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
        // Enable 7.1 nullable types support
        if ($this->parameterNode->type instanceof NullableType) {
            return true;
        }

        $hasDefaultNull = $this->isDefaultValueAvailable() && $this->getDefaultValue() === null;
        if ($hasDefaultNull) {
            return true;
        }

        if (!isset($this->parameterNode->type)) {
            return true;
        }

        return $this->parameterNode->default instanceof ConstFetch
            && strtolower($this->parameterNode->default->name->toString()) === 'null';
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
    public function getClass(): ?\ReflectionClass
    {
        $parameterType = $this->parameterNode->type;
        if ($parameterType instanceof Name) {
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
    public function getType(): ?\ReflectionType
    {
        $isBuiltin     = false;
        $parameterType = $this->parameterNode->type;
        if ($parameterType instanceof NullableType) {
            $parameterType = $parameterType->type;
        }

        $allowsNull = $this->allowsNull();
        if ($parameterType instanceof Identifier) {
            $isBuiltin = true;
            $parameterType = $parameterType->toString();
        } elseif (is_object($parameterType)) {
            $parameterType = $parameterType->toString();
        } elseif (is_string($parameterType)) {
            $isBuiltin = true;
        } else {
            return null;
        }

        if ($parameterType === 'iterable') {
            $parameterType = 'Traversable|array';
        }

        return new ReflectionNamedType($parameterType, $allowsNull, $isBuiltin);
    }

    /**
     * @inheritDoc
     */
    public function hasType(): bool
    {
        $hasType = isset($this->parameterNode->type);

        return $hasType;
    }

    /**
     * @inheritDoc
     */
    public function isArray(): bool
    {
        $type = $this->parameterNode->type;

        return ($type instanceof Identifier) && 'array' === $type->name;
    }

    /**
     * @inheritDoc
     */
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
        if (! isset($this->parameterNode->default)) {
            return false;
        }

        // start from PHP 8.1, isDefaultValueAvailable() returns false if next parameter is required
        // see https://github.com/php/php-src/issues/8090
        $parameters = $this->declaringFunction->getNode()->getParams();
        for ($key = $this->parameterIndex + 1; $key < count($parameters); ++$key) {
            if (! $parameters[$key]->default instanceof Expr) {
                return false;
            }
        }

        return true;
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
        return $this->isVariadic() || ($this->isDefaultValueAvailable() && $this->haveSiblingsDefaultValues());
    }

    /**
     * @inheritDoc
     */
    public function isPassedByReference(): bool
    {
        return (bool) $this->parameterNode->byRef;
    }

    /**
     * @inheritDoc
     */
    public function isVariadic(): bool
    {
        return (bool) $this->parameterNode->variadic;
    }

    /**
     * Returns if all following parameters have a default value definition.
     *
     * @throws ReflectionException If could not fetch declaring function reflection
     */
    protected function haveSiblingsDefaultValues(): bool
    {
        $function = $this->getDeclaringFunction();
        if (null === $function) {
            throw new ReflectionException('Could not get the declaring function reflection.');
        }

        /** @var BaseReflectionParameter[] $remainingParameters */
        $remainingParameters = array_slice($function->getParameters(), $this->parameterIndex + 1);
        foreach ($remainingParameters as $reflectionParameter) {
            if (!$reflectionParameter->isDefaultValueAvailable()) {
                return false;
            }
        }

        return true;
    }
}
