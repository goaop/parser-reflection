<?php
/**
 * Parser Reflection API
 *
 * @copyright Copyright 2015, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace ParserReflection\ValueResolver;

use PhpParser\Node;
use PhpParser\Node\Scalar\MagicConst;

/**
 * Tries to resolve expression into value
 */
class NodeExpressionResolver
{

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
        $this->value = null;

        // Most of simple scalars have raw value
        if ($node instanceof Node\Scalar && isset($node->value)) {
            $this->value = $node->value;
        }

        if ($node instanceof MagicConst) {
            $this->value = $this->resolveMagicConst($node);
        }
    }

    private function resolveMagicConst(MagicConst $node)
    {
        if ($node instanceof MagicConst\Method) {
            if ($this->context instanceof \ReflectionMethod) {
                $fullName = $this->context->getDeclaringClass()->getName() . '::' . $this->context->getShortName();

                return $fullName;
            }
        }

        if ($node instanceof MagicConst\Function_) {
            if ($this->context instanceof \ReflectionFunctionAbstract) {
                return $this->context->getName();
            }
        }

        if ($node instanceof MagicConst\Namespace_) {
            if (method_exists($this->context, 'getNamespaceName')) {
                return $this->context->getNamespaceName();
            }
        }

        if ($node instanceof MagicConst\Class_) {
            if ($this->context instanceof \ReflectionClass) {
                return $this->context->getName();
            }
            if (method_exists($this->context, 'getDeclaringClass')) {
                return $this->context->getDeclaringClass()->getName();
            }
        }

        if ($node instanceof MagicConst\Dir) {
            if (method_exists($this->context, 'getFileName')) {
                return dirname($this->context->getFileName());
            }
        }

        if ($node instanceof MagicConst\File) {
            if (method_exists($this->context, 'getFileName')) {
                return $this->context->getFileName();
            }
        }

        if ($node instanceof MagicConst\Line) {
            return $node->hasAttribute('startLine') ? $node->getAttribute('startLine') : 0;
        }

        if ($node instanceof MagicConst\Trait_) {
            if ($this->context instanceof \ReflectionClass && $this->context->isTrait()) {
                return $this->context->getName();
            }
        }

        return '';
    }
}
