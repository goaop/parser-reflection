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
use ParserReflection\ValueResolver\NodeExpressionResolver;
use PhpParser\Node\Name;
use PhpParser\Node\Param;
use ReflectionParameter as BaseReflectionParameter;

/**
 * AST-based reflection for method/function parameter
 */
class ReflectionParameter extends BaseReflectionParameter
{
    use InitializationTrait;

    /**
     * Reflection function or method
     *
     * @var \ReflectionFunctionAbstract
     */
    private $declaringFunction;

    /**
     * Stores the default value for node (if present)
     *
     * @var mixed
     */
    private $defaultValue = null;

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
     * Name of the function/method
     *
     * @var string
     */
    private $functionName;

    /**
     * Index of parameter in the list
     *
     * @var int
     */
    private $parameterIndex = 0;

    /**
     * Concrete parameter node
     *
     * @var Param
     */
    private $parameterNode;

    /**
     * Initializes a reflection for the property
     *
     * @param string|array $functionName Name of the function/method
     * @param string $parameterName Name of the parameter to reflect
     * @param Param $parameterNode Parameter definition node
     * @param int $parameterIndex Index of parameter
     */
    public function __construct(
        $functionName,
        $parameterName,
        Param $parameterNode = null,
        $parameterIndex = 0)
    {
        $this->functionName = $functionName;
        if (!$parameterNode) {
            list ($paramNode, $parameterNode) = ReflectionEngine::parseClassProperty($functionName, $parameterName);
        }

        $this->parameterNode  = $parameterNode;
        $this->parameterIndex = $parameterIndex;

        if ($this->isDefaultValueAvailable()) {
            $expressionSolver = new NodeExpressionResolver(null);
            $expressionSolver->process($this->parameterNode->default);
            $this->defaultValue             = $expressionSolver->getValue();
            $this->isDefaultValueConstant   = $expressionSolver->isConstant();
            $this->defaultValueConstantName = $expressionSolver->getConstantName();
        }
    }

    /**
     * Emulating original behaviour of reflection
     */
    public function __debugInfo()
    {
        return array(
            'name' => $this->parameterNode->name,
        );
    }

    /**
     * Returns string representation of this parameter.
     *
     * @return string
     */
    public function __toString()
    {
        $isNullableObjectParam = $this->allowsNull();
        $parameterType         = $this->parameterNode->type;
        if (is_object($parameterType)) {
            $parameterType = $parameterType->toString();
        }

        return sprintf(
            'Parameter #%d [ %s %s%s%s%s$%s%s ]',
            $this->parameterIndex,
            ($this->isVariadic() || $this->isOptional()) ? '<optional>' : '<required>',
            $parameterType ? ltrim($parameterType, '\\') . ' ' : '',
            $isNullableObjectParam ? 'or NULL ' : '',
            $this->isVariadic() ? '...' : '',
            $this->isPassedByReference() ? '&' : '',
            $this->getName(),
            $this->isOptional()
                ? (' = ' . var_export($this->getDefaultValue(), true))
                : ''
        );
    }

    /**
     * {@inheritDoc}
     */
    public function allowsNull()
    {
        $hasDefaultNull = $this->isDefaultValueAvailable() && $this->getDefaultValue() === null;
        if ($hasDefaultNull) {
            return true;
        }

        return !isset($this->parameterNode->type);
    }

    /**
     * {@inheritDoc}
     */
    public function canBePassedByValue()
    {
        return !$this->isPassedByReference();
    }

    /**
     * @inheritDoc
     */
    public function getClass()
    {
        $parameterType = $this->parameterNode->type;
        if ($parameterType instanceof Name) {
            if (!$parameterType instanceof Name\FullyQualified) {
                throw new ReflectionException("Can not resolve a class name for parameter");
            }
            $className   = $parameterType->toString();
            $classExists = class_exists($className, false);

            return $classExists ? new \ReflectionClass($className) : new ReflectionClass($className);
        }

        return null;
    }

    /**
     * {@inheritDoc}
     */
    public function getDeclaringClass()
    {
        if ($this->declaringFunction instanceof \ReflectionMethod) {
            return $this->declaringFunction->getDeclaringClass();
        };

        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function getDeclaringFunction()
    {
        return $this->declaringFunction;
    }

    /**
     * {@inheritDoc}
     */
    public function getDefaultValue()
    {
        if (!$this->isDefaultValueAvailable()) {
            throw new ReflectionException("Can not get the default value for the parameter");
        }

        return $this->defaultValue;
    }

    /**
     * {@inheritDoc}
     */
    public function getDefaultValueConstantName()
    {
        if (!$this->isDefaultValueAvailable()) {
            throw new ReflectionException("Can not get the default value for the parameter");
        }

        return $this->defaultValueConstantName;
    }

    /**
     * @inheritDoc
     */
    public function getName()
    {
        return $this->parameterNode->name;
    }

    /**
     * {@inheritDoc}
     */
    public function getPosition()
    {
        return $this->parameterIndex;
    }

    /**
     * @inheritDoc
     */
    public function isArray()
    {
        return 'array' === $this->parameterNode->type;
    }

    /**
     * @inheritDoc
     */
    public function isCallable()
    {
        return 'callable' === $this->parameterNode->type;
    }

    /**
     * @inheritDoc
     */
    public function isDefaultValueAvailable()
    {
        return isset($this->parameterNode->default);
    }

    /**
     * {@inheritDoc}
     */
    public function isDefaultValueConstant()
    {
        return $this->isDefaultValueConstant;
    }

    /**
     * {@inheritDoc}
     */
    public function isOptional()
    {
        return $this->isDefaultValueAvailable() && $this->haveSiblingsDefalutValues();
    }

    /**
     * @inheritDoc
     */
    public function isPassedByReference()
    {
        return (bool) $this->parameterNode->byRef;
    }

    /**
     * @inheritDoc
     */
    public function isVariadic()
    {
        return (bool) $this->parameterNode->variadic;
    }

    /**
     * Passes an information about top-level reflection instance
     *
     * @param \ReflectionFunctionAbstract $refFunction
     */
    public function setDeclaringFunction(\ReflectionFunctionAbstract $refFunction)
    {
        $this->declaringFunction = $refFunction;
    }

    /**
     * Returns if all following parameters have a default value definition.
     *
     * @return bool
     * @throws ReflectionException If could not fetch declaring function reflection
     */
    protected function haveSiblingsDefalutValues()
    {
        $function = $this->getDeclaringFunction();
        if (null === $function) {
            throw new ReflectionException('Could not get the declaring function reflection.');
        }

        /** @var \ReflectionParameter[] $remainingParameters */
        $remainingParameters = array_slice($function->getParameters(), $this->parameterIndex + 1);
        foreach ($remainingParameters as $reflectionParameter) {
            if (!$reflectionParameter->isDefaultValueAvailable()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Implementation of internal reflection initialization
     *
     * @return void
     */
    protected function __initialize()
    {
        parent::__construct($this->functionName, $this->getName());
    }
}