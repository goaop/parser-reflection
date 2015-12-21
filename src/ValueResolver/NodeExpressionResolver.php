<?php
/**
 * Parser Reflection API
 *
 * @copyright Copyright 2015, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\ParserReflection\ValueResolver;

use Go\ParserReflection\ReflectionClass;
use Go\ParserReflection\ReflectionException;
use Go\ParserReflection\ReflectionFileNamespace;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Scalar;
use PhpParser\Node\Scalar\MagicConst;

/**
 * Tries to resolve expression into value
 */
class NodeExpressionResolver
{

    /**
     * List of exception for constant fetch
     *
     * @var array
     */
    private static $notConstants = [
        'true'  => true,
        'false' => true,
        'null'  => true,
    ];

    /**
     * Name of the constant (if present)
     *
     * @var null|string
     */
    private $constantName = null;

    /**
     * Current reflection context for parsing
     *
     * @var mixed
     */
    private $context;

    /**
     * Flag if expression is constant
     *
     * @var bool
     */
    private $isConstant = false;

    /**
     * Node resolving level, 1 = top-level
     *
     * @var int
     */
    private $nodeLevel = 0;

    /**
     * @var mixed Value of expression/constant
     */
    private $value;

    public function __construct($context)
    {
        $this->context = $context;
    }

    public function getConstantName()
    {
        return $this->constantName;
    }

    public function getValue()
    {
        return $this->value;
    }

    public function isConstant()
    {
        return $this->isConstant;
    }

    /**
     * {@inheritDoc}
     */
    public function process(Node $node)
    {
        $this->nodeLevel    = 0;
        $this->isConstant   = false;
        $this->constantName = null;
        $this->value        = $this->resolve($node);
    }

    /**
     * Resolves node into valid value
     *
     * @param Node $node
     *
     * @return mixed
     */
    protected function resolve(Node $node)
    {
        $value = null;
        try {
            ++$this->nodeLevel;

            $nodeType   = $node->getType();
            $methodName = 'resolve' . str_replace('_', '', $nodeType);
            if (method_exists($this, $methodName)) {
                $value = $this->$methodName($node);
            }
        } finally {
            --$this->nodeLevel;
        }

        return $value;
    }

    protected function resolveScalarDNumber(Scalar\DNumber $node)
    {
        return $node->value;
    }

    protected function resolveScalarLNumber(Scalar\LNumber $node)
    {
        return $node->value;
    }

    protected function resolveScalarString(Scalar\String_ $node)
    {
        return $node->value;
    }

    protected function resolveScalarMagicConstMethod()
    {
        if ($this->context instanceof \ReflectionMethod) {
            $fullName = $this->context->getDeclaringClass()->getName() . '::' . $this->context->getShortName();

            return $fullName;
        }

        return '';
    }

    protected function resolveScalarMagicConstFunction()
    {
        if ($this->context instanceof \ReflectionFunctionAbstract) {
            return $this->context->getName();
        }

        return '';
    }

    protected function resolveScalarMagicConstNamespace()
    {
        if (method_exists($this->context, 'getNamespaceName')) {
            return $this->context->getNamespaceName();
        }

        if ($this->context instanceof ReflectionFileNamespace) {
            return $this->context->getName();
        }

        return '';
    }

    protected function resolveScalarMagicConstClass()
    {
        if ($this->context instanceof \ReflectionClass) {
            return $this->context->getName();
        }
        if (method_exists($this->context, 'getDeclaringClass')) {
            $declaringClass = $this->context->getDeclaringClass();
            if ($declaringClass instanceof \ReflectionClass) {
                return $declaringClass->getName();
            }
        }

        return '';
    }

    protected function resolveScalarMagicConstDir()
    {
        if (method_exists($this->context, 'getFileName')) {
            return dirname($this->context->getFileName());
        }

        return '';
    }

    protected function resolveScalarMagicConstFile()
    {
        if (method_exists($this->context, 'getFileName')) {
            return $this->context->getFileName();
        }

        return '';
    }

    protected function resolveScalarMagicConstLine(MagicConst\Line $node)
    {
        return $node->hasAttribute('startLine') ? $node->getAttribute('startLine') : 0;
    }

    protected function resolveScalarMagicConstTrait()
    {
        if ($this->context instanceof \ReflectionClass && $this->context->isTrait()) {
            return $this->context->getName();
        }

        return '';
    }

    protected function resolveExprConstFetch(Expr\ConstFetch $node)
    {
        $constantValue = null;
        $isResolved    = false;

        /** @var ReflectionFileNamespace|null $fileNamespace */
        $fileNamespace = null;
        $isFQNConstant = $node->name instanceof Node\Name\FullyQualified;
        $constantName  = $node->name->toString();

        if (!$isFQNConstant) {
            if (method_exists($this->context, 'getFileName')) {
                $fileName      = $this->context->getFileName();
                $namespaceName = $this->context->getNamespaceName();
                $fileNamespace = new ReflectionFileNamespace($fileName, $namespaceName);
                if ($fileNamespace->hasConstant($constantName)) {
                    $constantValue = $fileNamespace->getConstant($constantName);
                    $constantName  = $fileNamespace->getName() . '\\' . $constantName;
                    $isResolved    = true;
                }
            }
        }

        if (!$isResolved && defined($constantName)) {
            $constantValue = constant($constantName);
        }

        if ($this->nodeLevel === 1 && !isset(self::$notConstants[$constantName])) {
            $this->isConstant   = true;
            $this->constantName = $constantName;
        }

        return $constantValue;
    }

    protected function resolveExprClassConstFetch(Expr\ClassConstFetch $node)
    {
        $refClass     = $this->fetchReflectionClass($node->class);
        $constantName = $node->name;

        // special handling of ::class constants
        if ('class' === $constantName) {
            return $refClass->getName();
        }

        return $refClass->getConstant($constantName);
    }

    protected function resolveExprArray(Expr\Array_ $node)
    {
        $result = [];
        foreach ($node->items as $itemIndex => $arrayItem) {
            $itemValue = $this->resolve($arrayItem->value);
            $itemKey   = isset($arrayItem->key) ? $this->resolve($arrayItem->key) : $itemIndex;
            $result[$itemKey] = $itemValue;
        }

        return $result;
    }

    /**
     * Utility method to fetch reflection class instance by name
     *
     * Supports:
     *   'self' keyword
     *   'parent' keyword
     *    not-FQN class names
     *
     * @param Node\Name $node Class name node
     *
     * @return bool|\ReflectionClass
     *
     * @throws ReflectionException
     */
    private function fetchReflectionClass(Node\Name $node)
    {
        $className  = $node->toString();
        $isFQNClass = $node instanceof Node\Name\FullyQualified;
        if ($isFQNClass) {
            return new ReflectionClass($className);
        }

        if ('self' === $className) {
            if ($this->context instanceof \ReflectionClass) {
                return $this->context;
            } elseif (method_exists($this->context, 'getDeclaringClass')) {
                return $this->context->getDeclaringClass();
            }
        }

        if ('parent' === $className) {
            if ($this->context instanceof \ReflectionClass) {
                return $this->context->getParentClass();
            } elseif (method_exists($this->context, 'getDeclaringClass')) {
                return $this->context->getDeclaringClass()->getParentClass();
            }
        }

        if (method_exists($this->context, 'getFileName')) {
            /** @var ReflectionFileNamespace|null $fileNamespace */
            $fileName      = $this->context->getFileName();
            $namespaceName = $this->context->getNamespaceName();

            $fileNamespace = new ReflectionFileNamespace($fileName, $namespaceName);
            return $fileNamespace->getClass($className);
        }

        throw new ReflectionException("Can not resolve class $className");
    }
}
