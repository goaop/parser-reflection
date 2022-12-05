<?php
/**
 * Parser Reflection API
 *
 * @copyright Copyright 2015-2022, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */
declare(strict_types=1);

namespace Go\ParserReflection;

use Go\ParserReflection\Traits\CanHoldAttributesTrait;
use Go\ParserReflection\Traits\InternalPropertiesEmulationTrait;
use Go\ParserReflection\ValueResolver\NodeExpressionResolver;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\Param;
use PhpParser\Node\UnionType;
use ReflectionClass as BaseReflectionClass;
use ReflectionFunctionAbstract;
use ReflectionParameter as BaseReflectionParameter;
use ReflectionType as BaseReflectionType;

/**
 * AST-based reflection for method/function parameter
 *
 * @template T of object
 */
class ReflectionParameter extends BaseReflectionParameter
{
    use InternalPropertiesEmulationTrait;
    use CanHoldAttributesTrait;

    /**
     * Reflection function or method
     *
     * @var ReflectionFunctionAbstract
     */
    private ReflectionFunctionAbstract $declaringFunction;

    /**
     * Stores the default value for node (if present)
     *
     * @var mixed
     */
    private mixed $defaultValue;

    /**
     * Whether default value is constant
     *
     * @var bool
     */
    private bool $isDefaultValueConstant = false;

    /**
     * Name of the constant of default value
     *
     * @var string|null
     */
    private ?string $defaultValueConstantName = null;

    /**
     * Index of parameter in the list
     *
     * @var int
     */
    private int $parameterIndex;

    /**
     * Concrete parameter node
     *
     * @var Param
     */
    private Param $parameterNode;

    /**
     * Initializes a reflection for the property
     *
     * @param string|array                $unusedFunctionName Name of the function/method
     * @param string                      $parameterName      Name of the parameter to reflect
     * @param ?Param                      $parameterNode      Parameter definition node
     * @param int                         $parameterIndex     Index of parameter
     * @param ?ReflectionFunctionAbstract $declaringFunction
     *
     * @noinspection PhpMissingParentConstructorInspection
     * @noinspection PhpUnusedParameterInspection
     */
    public function __construct(
        string|array $unusedFunctionName,
        string $parameterName,
        Param $parameterNode = null,
        int $parameterIndex = 0,
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

            $expressionSolver = new NodeExpressionResolver($context);
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
    public function getNode(): Param
    {
        return $this->parameterNode;
    }

    /**
     * Emulating original behaviour of reflection.
     *
     * Called when invoking {@link var_dump()} on an object
     *
     * @return array{name: string}
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
     *
     * @noinspection PhpDocMissingThrowsInspection
     */
    public function __toString()
    {
        $parameterType   = $this->getType();
        $isOptional      = $this->isOptional();
        $hasDefaultValue = $this->isDefaultValueAvailable();
        $defaultValue    = '';
        if ($hasDefaultValue) {
            /** @noinspection PhpUnhandledExceptionInspection */
            $defaultValue = $this->getDefaultValue();
            if (is_string($defaultValue) && strlen($defaultValue) > 15) {
                $defaultValue = substr($defaultValue, 0, 15) . '...';
            }
            /* @see https://3v4l.org/DJOEb for behaviour changes */
            if (is_float($defaultValue) && fmod($defaultValue, 1.0) === 0.0) {
                $defaultValue = (int)$defaultValue;
            }

            $defaultValue = str_replace('\\\\', '\\', var_export($defaultValue, true));
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

        return !isset($this->parameterNode->type);
    }

    /**
     * {@inheritDoc}
     */
    public function canBePassedByValue(): ?bool
    {
        return !$this->isPassedByReference();
    }

    /**
     * {@inheritDoc}
     */
    public function getClass(): BaseReflectionClass|null
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

                return null;
            }
            $className = $parameterType->toString();

            $classOrInterfaceExists = class_exists($className, false) || interface_exists($className, false);

            /** @noinspection PhpUnhandledExceptionInspection */
            return $classOrInterfaceExists ? new BaseReflectionClass($className) : new ReflectionClass($className);
        }

        return null;
    }

    /**
     * {@inheritDoc}
     */
    public function getDeclaringClass(): ?BaseReflectionClass
    {
        if ($this->declaringFunction instanceof \ReflectionMethod) {
            return $this->declaringFunction->getDeclaringClass();
        }

        return null;
    }

    /**
     * {@inheritDoc}
     */
    public function getDeclaringFunction(): ?ReflectionFunctionAbstract
    {
        return $this->declaringFunction;
    }

    /**
     * {@inheritDoc}
     */
    public function getDefaultValue()
    {
        if (!$this->isDefaultValueAvailable()) {
            throw new ReflectionException('Internal error: Failed to retrieve the default value');
        }

        return $this->defaultValue;
    }

    /**
     * {@inheritDoc}
     */
    public function getDefaultValueConstantName(): ?string
    {
        if (!$this->isDefaultValueAvailable()) {
            throw new ReflectionException('Internal error: Failed to retrieve the default value');
        }

        return $this->defaultValueConstantName;
    }

    /**
     * {@inheritDoc}
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
     * Gets a parameter's type
     *
     * @link https://php.net/manual/en/reflectionparameter.gettype.php
     *
     * @return BaseReflectionType|null Returns a {@see BaseReflectionType} object if a
     *                                 parameter type is specified, {@see null} otherwise.
     */
    public function getType(): BaseReflectionType|null
    {
        $parameterType = $this->parameterNode->type;
        if ($parameterType instanceof NullableType) {
            $parameterType = $parameterType->type;
        }

        elseif ($parameterType instanceof UnionType) {
            $types = [];
            foreach ($parameterType->types as $type) {
                $types[] = $this->resolveReflectionNamedType($type);
            }

            $allowsNull = $this->allowsNull();
            return new ReflectionUnionType($types, $allowsNull);
        }

        return $this->resolveReflectionNamedType($parameterType);
    }

    /**
     * Resolve a ReflectionNamedType from a Node
     *
     * @param Identifier|Name|null $parameterType
     *
     * @return ReflectionNamedType|null
     */
    private function resolveReflectionNamedType(Identifier|Name|null $parameterType): ReflectionNamedType|null
    {
        $isBuiltin = false;
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

        return new ReflectionNamedType($parameterType, $allowsNull, $isBuiltin);
    }

    /**
     * {@inheritDoc}
     */
    public function hasType(): bool
    {
        $hasType = isset($this->parameterNode->type);

        return $hasType;
    }

    /**
     * {@inheritDoc}
     */
    public function isArray(): bool
    {
        $type = $this->parameterNode->type;

        return ($type instanceof Identifier) && 'array' === $type->name;
    }

    /**
     * {@inheritDoc}
     */
    public function isCallable(): ?bool
    {
        $type = $this->parameterNode->type;

        return ($type instanceof Identifier) && 'callable' === $type->name;
    }

    /**
     * {@inheritDoc}
     */
    public function isDefaultValueAvailable(): bool
    {
        return isset($this->parameterNode->default);
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
     * {@inheritDoc}
     */
    public function isPassedByReference(): bool
    {
        return $this->parameterNode->byRef;
    }

    /**
     * {@inheritDoc}
     */
    public function isVariadic(): bool
    {
        return $this->parameterNode->variadic;
    }

    /**
     * Returns an array of parameter attributes.
     *
     * @template T
     *
     * @param class-string<T>|null $name  Name of an attribute class
     * @param int                  $flags Criteria by which the attribute is searched.
     *
     * @return ReflectionAttribute<T>[]
     */
    public function getAttributes(?string $name = null, int $flags = 0): array
    {
        if (!isset($this->attributes)) {
            $this->collectAttributes();
        }

        return $this->attributes;
    }

    /**
     * Returns if all following parameters have a default value definition.
     *
     * @return bool
     */
    protected function haveSiblingsDefaultValues(): bool
    {
        $function = $this->getDeclaringFunction();
        if (null === $function) {
            return false;
        }

        $remainingParameters = array_slice($function->getParameters(), $this->parameterIndex + 1);
        foreach ($remainingParameters as $reflectionParameter) {
            if (!$reflectionParameter->isDefaultValueAvailable()) {
                return false;
            }
        }

        return true;
    }
}
