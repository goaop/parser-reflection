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
use PhpParser\Node\Param;
use ReflectionParameter as BaseReflectionParameter;

/**
 * AST-based reflection for method/function parameter
 */
class ReflectionParameter extends BaseReflectionParameter
{
    use InitializationTrait;

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
     * {@inheritDoc}
     */
    public function canBePassedByValue()
    {
        return !$this->isPassedByReference();
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
     * Implementation of internal reflection initialization
     *
     * @return void
     */
    protected function __initialize()
    {
        parent::__construct($this->functionName, $this->getName());
    }
}