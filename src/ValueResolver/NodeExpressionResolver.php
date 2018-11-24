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
use PhpParser\Node\Stmt\Expression;

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
     * @var mixed|\Go\ParserReflection\ReflectionClass
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
        // Unwrap "expr;" statements.
        if ($node instanceof Expression) {
            $node = $node->expr;
        }

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

            $methodName = $this->getDispatchMethodFor($node);
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
            $fullName = $this->context->getDeclaringClass()->name . '::' . $this->context->getShortName();

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
            return $this->context->name;
        }
        if (method_exists($this->context, 'getDeclaringClass')) {
            $declaringClass = $this->context->getDeclaringClass();
            if ($declaringClass instanceof \ReflectionClass) {
                return $declaringClass->name;
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
            return $this->context->name;
        }

        return '';
    }

    protected function resolveExprConstFetch(Expr\ConstFetch $node)
    {
        $constantValue = null;
        $isResolved    = false;

        $isFQNConstant = $node->name instanceof Node\Name\FullyQualified;
        $constantName  = $node->name->toString();

        if (!$isFQNConstant) {
            if (method_exists($this->context, 'getFileName')) {
                $fileName      = $this->context->getFileName();
                $namespaceName = $this->resolveScalarMagicConstNamespace();
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
        $classToReflect = $node->class;
        if (!($classToReflect instanceof Node\Name)) {
            $classToReflect = $this->resolve($classToReflect) ?: $classToReflect;
            if (!is_string($classToReflect)) {
                $reason = 'Unable';
                if ($classToReflect instanceof Expr) {
                    $methodName = $this->getDispatchMethodFor($classToReflect);
                    $reason = "Method " . __CLASS__ . "::{$methodName}() not found trying";
                }
                throw new ReflectionException("$reason to resolve class constant.");
            }
            // Strings evaluated as class names are always treated as fully
            // qualified.
            $classToReflect = new Node\Name\FullyQualified(ltrim($classToReflect, '\\'));
        }
        $refClass = $this->fetchReflectionClass($classToReflect);
        $constantName = ($node->name instanceof Expr\Error) ? '' : $node->name->toString();

        // special handling of ::class constants
        if ('class' === $constantName) {
            return $refClass->getName();
        }

        $this->isConstant = true;
        $this->constantName = (string)$classToReflect . '::' . $constantName;

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

    protected function resolveExprBinaryOpPlus(Expr\BinaryOp\Plus $node)
    {
        return $this->resolve($node->left) + $this->resolve($node->right);
    }

    protected function resolveExprBinaryOpMinus(Expr\BinaryOp\Minus $node)
    {
        return $this->resolve($node->left) - $this->resolve($node->right);
    }

    protected function resolveExprBinaryOpMul(Expr\BinaryOp\Mul $node)
    {
        return $this->resolve($node->left) * $this->resolve($node->right);
    }

    protected function resolveExprBinaryOpPow(Expr\BinaryOp\Pow $node)
    {
        return pow($this->resolve($node->left), $this->resolve($node->right));
    }

    protected function resolveExprBinaryOpDiv(Expr\BinaryOp\Div $node)
    {
        return $this->resolve($node->left) / $this->resolve($node->right);
    }

    protected function resolveExprBinaryOpMod(Expr\BinaryOp\Mod $node)
    {
        return $this->resolve($node->left) % $this->resolve($node->right);
    }

    protected function resolveExprBooleanNot(Expr\BooleanNot $node)
    {
        return !$this->resolve($node->expr);
    }

    protected function resolveExprBitwiseNot(Expr\BitwiseNot $node)
    {
        return ~$this->resolve($node->expr);
    }

    protected function resolveExprBinaryOpBitwiseOr(Expr\BinaryOp\BitwiseOr $node)
    {
        return $this->resolve($node->left) | $this->resolve($node->right);
    }

    protected function resolveExprBinaryOpBitwiseAnd(Expr\BinaryOp\BitwiseAnd $node)
    {
        return $this->resolve($node->left) & $this->resolve($node->right);
    }

    protected function resolveExprBinaryOpBitwiseXor(Expr\BinaryOp\BitwiseXor $node)
    {
        return $this->resolve($node->left) ^ $this->resolve($node->right);
    }

    protected function resolveExprBinaryOpShiftLeft(Expr\BinaryOp\ShiftLeft $node)
    {
        return $this->resolve($node->left) << $this->resolve($node->right);
    }

    protected function resolveExprBinaryOpShiftRight(Expr\BinaryOp\ShiftRight $node)
    {
        return $this->resolve($node->left) >> $this->resolve($node->right);
    }

    protected function resolveExprBinaryOpConcat(Expr\BinaryOp\Concat $node)
    {
        return $this->resolve($node->left) . $this->resolve($node->right);
    }

    protected function resolveExprTernary(Expr\Ternary $node)
    {
        if (isset($node->if)) {
            // Full syntax $a ? $b : $c;

            return $this->resolve($node->cond) ? $this->resolve($node->if) : $this->resolve($node->else);
        } else {
            // Short syntax $a ?: $c;

            return $this->resolve($node->cond) ?: $this->resolve($node->else);
        }
    }

    protected function resolveExprBinaryOpSmallerOrEqual(Expr\BinaryOp\SmallerOrEqual $node)
    {
        return $this->resolve($node->left) <= $this->resolve($node->right);
    }

    protected function resolveExprBinaryOpGreaterOrEqual(Expr\BinaryOp\GreaterOrEqual $node)
    {
        return $this->resolve($node->left) >= $this->resolve($node->right);
    }

    protected function resolveExprBinaryOpEqual(Expr\BinaryOp\Equal $node)
    {
        return $this->resolve($node->left) == $this->resolve($node->right);
    }

    protected function resolveExprBinaryOpNotEqual(Expr\BinaryOp\NotEqual $node)
    {
        return $this->resolve($node->left) != $this->resolve($node->right);
    }

    protected function resolveExprBinaryOpSmaller(Expr\BinaryOp\Smaller $node)
    {
        return $this->resolve($node->left) < $this->resolve($node->right);
    }

    protected function resolveExprBinaryOpGreater(Expr\BinaryOp\Greater $node)
    {
        return $this->resolve($node->left) > $this->resolve($node->right);
    }

    protected function resolveExprBinaryOpIdentical(Expr\BinaryOp\Identical $node)
    {
        return $this->resolve($node->left) === $this->resolve($node->right);
    }

    protected function resolveExprBinaryOpNotIdentical(Expr\BinaryOp\NotIdentical $node)
    {
        return $this->resolve($node->left) !== $this->resolve($node->right);
    }

    protected function resolveExprBinaryOpBooleanAnd(Expr\BinaryOp\BooleanAnd $node)
    {
        return $this->resolve($node->left) && $this->resolve($node->right);
    }

    protected function resolveExprBinaryOpLogicalAnd(Expr\BinaryOp\LogicalAnd $node)
    {
        return $this->resolve($node->left) and $this->resolve($node->right);
    }

    protected function resolveExprBinaryOpBooleanOr(Expr\BinaryOp\BooleanOr $node)
    {
        return $this->resolve($node->left) || $this->resolve($node->right);
    }

    protected function resolveExprBinaryOpLogicalOr(Expr\BinaryOp\LogicalOr $node)
    {
        return $this->resolve($node->left) or $this->resolve($node->right);
    }

    protected function resolveExprBinaryOpLogicalXor(Expr\BinaryOp\LogicalXor $node)
    {
        return $this->resolve($node->left) xor $this->resolve($node->right);
    }

    private function getDispatchMethodFor(Node $node)
    {
        $nodeType = $node->getType();
        return 'resolve' . str_replace('_', '', $nodeType);
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
            // check to see if the class is already loaded and is safe to use
            // PHP's ReflectionClass to determine if the class is user defined
            if (class_exists($className, false)) {
                $refClass = new \ReflectionClass($className);
                if (!$refClass->isUserDefined()) {
                    return $refClass;
                }
            }
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
            $namespaceName = $this->resolveScalarMagicConstNamespace();

            $fileNamespace = new ReflectionFileNamespace($fileName, $namespaceName);
            return $fileNamespace->getClass($className);
        }

        throw new ReflectionException("Can not resolve class $className");
    }
}
