<?php

declare(strict_types=1);
/**
 * Parser Reflection API
 *
 * @copyright Copyright 2015-2022, Lisachenko Alexander <lisachenko.it@gmail.com>
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
use PhpParser\Node\Scalar\DNumber;
use PhpParser\Node\Scalar\LNumber;
use PhpParser\Node\Scalar\MagicConst\Line;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Expression;
use ReflectionFunctionAbstract;
use ReflectionMethod;

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
    private static array $notConstants = [
        'true'  => true,
        'false' => true,
        'null'  => true,
    ];

    /**
     * Name of the constant (if present)
     *
     * @var ?string
     */
    private ?string $constantName;

    /**
     * Current reflection context for parsing
     *
     * @var mixed|ReflectionClass
     */
    private mixed $context;

    /**
     * Flag if expression is constant
     *
     * @var bool
     */
    private bool $isConstant = false;

    /**
     * Node resolving level, 1 = top-level
     *
     * @var int
     */
    private int $nodeLevel = 0;

    /**
     * @var mixed Value of expression/constant
     */
    private mixed $value;

    public function __construct($context)
    {
        $this->context = $context;
    }

    public function getConstantName(): ?string
    {
        return $this->constantName;
    }

    public function getValue()
    {
        return $this->value;
    }

    public function isConstant(): bool
    {
        return $this->isConstant;
    }

    public function process(Node $node): void
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
    protected function resolve(Node $node): mixed
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

    protected function resolveScalarDNumber(DNumber $node): float
    {
        return $node->value;
    }

    protected function resolveScalarLNumber(LNumber $node): int
    {
        return $node->value;
    }

    protected function resolveScalarString(String_ $node): string
    {
        return $node->value;
    }

    protected function resolveScalarMagicConstMethod(): string
    {
        if ($this->context instanceof ReflectionMethod) {
            $fullName = $this->context->getDeclaringClass()->name . '::' . $this->context->getShortName();

            return $fullName;
        }

        return '';
    }

    protected function resolveScalarMagicConstFunction(): string
    {
        if ($this->context instanceof ReflectionFunctionAbstract) {
            return $this->context->getName();
        }

        return '';
    }

    protected function resolveScalarMagicConstNamespace(): string
    {
        if (method_exists($this->context, 'getNamespaceName')) {
            return $this->context->getNamespaceName();
        }

        if ($this->context instanceof ReflectionFileNamespace) {
            return $this->context->getName();
        }

        return '';
    }

    protected function resolveScalarMagicConstClass(): string
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

    protected function resolveScalarMagicConstDir(): string
    {
        if (method_exists($this->context, 'getFileName')) {
            return dirname($this->context->getFileName());
        }

        return '';
    }

    protected function resolveScalarMagicConstFile(): string
    {
        if (method_exists($this->context, 'getFileName')) {
            return $this->context->getFileName();
        }

        return '';
    }

    protected function resolveScalarMagicConstLine(Line $node): int
    {
        return $node->hasAttribute('startLine') ? $node->getAttribute('startLine') : 0;
    }

    protected function resolveScalarMagicConstTrait(): string
    {
        if ($this->context instanceof \ReflectionClass && $this->context->isTrait()) {
            return $this->context->name;
        }

        return '';
    }

    /**
     * @param Expr\ConstFetch $node
     *
     * @return bool|mixed|null
     *
     * @throws ReflectionException
     */
    protected function resolveExprConstFetch(Expr\ConstFetch $node): mixed
    {
        $constantValue = null;
        $isResolved    = false;

        $isFQNConstant = $node->name instanceof Node\Name\FullyQualified;
        $constantName  = $node->name->toString();

        if (!$isFQNConstant && method_exists($this->context, 'getFileName')) {
            $fileName      = $this->context->getFileName();
            $namespaceName = $this->resolveScalarMagicConstNamespace();
            $fileNamespace = new ReflectionFileNamespace($fileName, $namespaceName);
            if ($fileNamespace->hasConstant($constantName)) {
                $constantValue = $fileNamespace->getConstant($constantName);
                $constantName  = $fileNamespace->getName() . '\\' . $constantName;
                $isResolved    = true;
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

    /**
     * @param Expr\ClassConstFetch $node
     *
     * @return false|mixed|string
     *
     * @throws ReflectionException
     */
    protected function resolveExprClassConstFetch(Expr\ClassConstFetch $node): mixed
    {
        $classToReflect = $node->class;
        if (!($classToReflect instanceof Node\Name)) {
            $classToReflect = $this->resolve($classToReflect) ?: $classToReflect;
            if (!is_string($classToReflect)) {
                $reason = 'Unable';
                if ($classToReflect instanceof Expr) {
                    $methodName = $this->getDispatchMethodFor($classToReflect);
                    $reason     = "Method " . __CLASS__ . "::$methodName() not found trying";
                }
                throw new ReflectionException("$reason to resolve class constant.");
            }
            // Strings evaluated as class names are always treated as fully
            // qualified.
            $classToReflect = new Node\Name\FullyQualified(ltrim($classToReflect, '\\'));
        }
        $refClass     = $this->fetchReflectionClass($classToReflect);
        $constantName = ($node->name instanceof Expr\Error) ? '' : $node->name->toString();

        // special handling of ::class constants
        if ('class' === $constantName) {
            return $refClass->getName();
        }

        $this->isConstant   = true;
        $this->constantName = $classToReflect . '::' . $constantName;

        return $refClass->getConstant($constantName);
    }

    protected function resolveExprArray(Expr\Array_ $node): array
    {
        $result = [];
        foreach ($node->items as $itemIndex => $arrayItem) {
            $itemValue        = $this->resolve($arrayItem->value);
            $itemKey          = isset($arrayItem->key) ? $this->resolve($arrayItem->key) : $itemIndex;
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

    protected function resolveExprBinaryOpMul(Expr\BinaryOp\Mul $node): float|int
    {
        return $this->resolve($node->left) * $this->resolve($node->right);
    }

    protected function resolveExprBinaryOpPow(Expr\BinaryOp\Pow $node)
    {
        return $this->resolve($node->left) ** $this->resolve($node->right);
    }

    protected function resolveExprBinaryOpDiv(Expr\BinaryOp\Div $node): float|int
    {
        return $this->resolve($node->left) / $this->resolve($node->right);
    }

    protected function resolveExprBinaryOpMod(Expr\BinaryOp\Mod $node): int
    {
        return $this->resolve($node->left) % $this->resolve($node->right);
    }

    protected function resolveExprBooleanNot(Expr\BooleanNot $node): bool
    {
        return !$this->resolve($node->expr);
    }

    protected function resolveExprBitwiseNot(Expr\BitwiseNot $node): int|string
    {
        return ~$this->resolve($node->expr);
    }

    protected function resolveExprBinaryOpBitwiseOr(Expr\BinaryOp\BitwiseOr $node): int
    {
        return $this->resolve($node->left) | $this->resolve($node->right);
    }

    protected function resolveExprBinaryOpBitwiseAnd(Expr\BinaryOp\BitwiseAnd $node): int
    {
        return $this->resolve($node->left) & $this->resolve($node->right);
    }

    protected function resolveExprBinaryOpBitwiseXor(Expr\BinaryOp\BitwiseXor $node): int
    {
        return $this->resolve($node->left) ^ $this->resolve($node->right);
    }

    protected function resolveExprBinaryOpShiftLeft(Expr\BinaryOp\ShiftLeft $node): int
    {
        return $this->resolve($node->left) << $this->resolve($node->right);
    }

    protected function resolveExprBinaryOpShiftRight(Expr\BinaryOp\ShiftRight $node): int
    {
        return $this->resolve($node->left) >> $this->resolve($node->right);
    }

    protected function resolveExprBinaryOpConcat(Expr\BinaryOp\Concat $node): string
    {
        return $this->resolve($node->left) . $this->resolve($node->right);
    }

    protected function resolveExprTernary(Expr\Ternary $node)
    {
        if (isset($node->if)) {
            // Full syntax $a ? $b : $c;

            return $this->resolve($node->cond) ? $this->resolve($node->if) : $this->resolve($node->else);
        }

        return $this->resolve($node->cond) ?: $this->resolve($node->else);
    }

    protected function resolveExprBinaryOpSmallerOrEqual(Expr\BinaryOp\SmallerOrEqual $node): bool
    {
        return $this->resolve($node->left) <= $this->resolve($node->right);
    }

    protected function resolveExprBinaryOpGreaterOrEqual(Expr\BinaryOp\GreaterOrEqual $node): bool
    {
        return $this->resolve($node->left) >= $this->resolve($node->right);
    }

    protected function resolveExprBinaryOpEqual(Expr\BinaryOp\Equal $node): bool
    {
        /** @noinspection TypeUnsafeComparisonInspection */
        return $this->resolve($node->left) == $this->resolve($node->right);
    }

    protected function resolveExprBinaryOpNotEqual(Expr\BinaryOp\NotEqual $node): bool
    {
        /** @noinspection TypeUnsafeComparisonInspection */
        return $this->resolve($node->left) != $this->resolve($node->right);
    }

    protected function resolveExprBinaryOpSmaller(Expr\BinaryOp\Smaller $node): bool
    {
        return $this->resolve($node->left) < $this->resolve($node->right);
    }

    protected function resolveExprBinaryOpGreater(Expr\BinaryOp\Greater $node): bool
    {
        return $this->resolve($node->left) > $this->resolve($node->right);
    }

    protected function resolveExprBinaryOpIdentical(Expr\BinaryOp\Identical $node): bool
    {
        return $this->resolve($node->left) === $this->resolve($node->right);
    }

    protected function resolveExprBinaryOpNotIdentical(Expr\BinaryOp\NotIdentical $node): bool
    {
        return $this->resolve($node->left) !== $this->resolve($node->right);
    }

    protected function resolveExprBinaryOpBooleanAnd(Expr\BinaryOp\BooleanAnd $node): bool
    {
        return $this->resolve($node->left) && $this->resolve($node->right);
    }

    protected function resolveExprBinaryOpLogicalAnd(Expr\BinaryOp\LogicalAnd $node): bool
    {
        return $this->resolve($node->left) and $this->resolve($node->right);
    }

    protected function resolveExprBinaryOpBooleanOr(Expr\BinaryOp\BooleanOr $node): bool
    {
        return $this->resolve($node->left) || $this->resolve($node->right);
    }

    protected function resolveExprBinaryOpLogicalOr(Expr\BinaryOp\LogicalOr $node): bool
    {
        return $this->resolve($node->left) or $this->resolve($node->right);
    }

    protected function resolveExprBinaryOpLogicalXor(Expr\BinaryOp\LogicalXor $node): bool
    {
        return $this->resolve($node->left) xor $this->resolve($node->right);
    }

    private function getDispatchMethodFor(Node $node): string
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
     * @return bool|ReflectionClass
     *
     * @throws ReflectionException
     *
     * @noinspection PhpDocMissingThrowsInspection
     * @noinspection PhpMissingReturnTypeInspection
     */
    private function fetchReflectionClass(Node\Name $node)
    {
        $className  = $node->toString();
        $isFQNClass = $node instanceof Node\Name\FullyQualified;
        if ($isFQNClass) {
            // check to see if the class is already loaded and is safe to use
            // PHP's ReflectionClass to determine if the class is user defined
            if (class_exists($className, false)) {
                /** @noinspection PhpUnhandledExceptionInspection */
                $refClass = new \ReflectionClass($className);
                if (!$refClass->isUserDefined()) {
                    /** @noinspection PhpIncompatibleReturnTypeInspection */
                    return $refClass;
                }
            }

            return new ReflectionClass($className);
        }

        if ('self' === $className) {
            if ($this->context instanceof \ReflectionClass) {
                return $this->context;
            }

            if (method_exists($this->context, 'getDeclaringClass')) {
                return $this->context->getDeclaringClass();
            }
        }

        if ('parent' === $className) {
            if ($this->context instanceof \ReflectionClass) {
                return $this->context->getParentClass();
            }

            if (method_exists($this->context, 'getDeclaringClass')) {
                return $this->context->getDeclaringClass()
                                     ->getParentClass();
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
