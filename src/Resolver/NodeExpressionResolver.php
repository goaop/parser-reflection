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

namespace Go\ParserReflection\Resolver;

use Go\ParserReflection\ReflectionClass;
use Go\ParserReflection\ReflectionException;
use Go\ParserReflection\ReflectionFileNamespace;
use Go\ParserReflection\ReflectionNamedType;
use PhpParser\Node;
use PhpParser\Node\Const_;
use PhpParser\Node\Expr;
use PhpParser\Node\Name;
use PhpParser\Node\Param;
use PhpParser\Node\Scalar\Float_;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\MagicConst\Line;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Expression;
use PhpParser\PrettyPrinter\Standard;
use ReflectionFunctionAbstract;
use ReflectionMethod;

/**
 * Tries to resolve expression into value
 * @see \Go\ParserReflection\Resolver\NodeExpressionResolverTest
 */
class NodeExpressionResolver
{

    /**
     * List of exception for constant fetch
     */
    private static array $notConstants = [
        'true'  => true,
        'false' => true,
        'null'  => true,
    ];

    /**
     * Current reflection context for parsing
     */
    private
        \ReflectionClass|\ReflectionFunction|\ReflectionMethod|\ReflectionClassConstant|
        \ReflectionParameter|\ReflectionAttribute|\ReflectionProperty|ReflectionFileNamespace|null $context;

    /**
     * Flag if given expression is constant
     */
    private bool $isConstant = false;

    /**
     * If given expression is constant-like (used mostly for dumping string representation) of node in reflection
     */
    private bool $isConstExpr = false;

    /**
     * Name of the constant (if present), used to collect references to constants for misc places
     * @see $isConstant
     */
    private ?string $constantName;

    /**
     * Node resolving level, 1 = top-level
     */
    private int $nodeLevel = 0;

    /**
     * @var Node[]
     */
    private array $nodeStack = [];

    private mixed $value;

    public function __construct($context)
    {
        $this->context = $context;
    }

    public function getConstantName(): ?string
    {
        return $this->constantName;
    }

    public function getValue(): mixed
    {
        return $this->value;
    }

    public function isConstant(): bool
    {
        return $this->isConstant;
    }

    public function isConstExpression(): bool
    {
        return $this->isConstExpr;
    }

    public function getConstExpression(): ?string
    {
        $expression = null;
        if ($this->isConstExpr) {
            // Clone node to avoid possible side-effects
            $node = clone $this->nodeStack[$this->nodeLevel];
            if ($node instanceof Expr\ConstFetch) {
                $constantNodeName = $node->name;
                // Unpack fully-resolved name if we have it inside attribute
                if ($constantNodeName->hasAttribute('resolvedName')) {
                    $constantNodeName = $constantNodeName->getAttribute('resolvedName');
                }
                if ($constantNodeName->isFullyQualified()) {
                    // For full-qualified names we would like to remove leading "\"
                    $node->name = new Name(ltrim($constantNodeName->toString(), '\\'));
                } else {
                    // For relative names we would like to add namespace prefix
                    $node->name = new Name($this->resolveScalarMagicConstNamespace() . '\\' . $constantNodeName->toString());
                }
            }
            // All long array nodes are pretty-printed by PHP in short format
            if ($node instanceof Expr\Array_ && $node->getAttribute('kind') === Expr\Array_::KIND_LONG) {
                $node->setAttribute('kind', Expr\Array_::KIND_SHORT);
            }
            $printer    = new Standard(['shortArraySyntax' => true]);
            $expression = $printer->prettyPrintExpr($node);
        }

        return $expression;
    }

    /**
     * @throws ReflectionException If node could not be resolved
     */
    final public function process(Node $node): void
    {
        $this->nodeLevel    = 0;
        $this->nodeStack    = [$node]; // Always keep the root node
        $this->isConstant   = false;
        $this->isConstExpr  = false;
        $this->constantName = null;
        $this->value        = $this->resolve($node);
    }

    /**
     * Recursively resolves node into valid value
     *
     * @throws ReflectionException If couldn't resolve value for given Node
     */
    final protected function resolve(Node $node): mixed
    {
        $value = null;
        try {
            $this->nodeStack[] = $node;
            ++$this->nodeLevel;
            if ($this->nodeLevel > 1 && $this->isConstant) {
                $this->isConstant   = false;
                $this->constantName = null;
            }

            $methodName = $this->getDispatchMethodFor($node);
            if (!method_exists($this, $methodName)) {
                throw new ReflectionException("Could not find handler for the " . __CLASS__ . "::{$methodName} method");
            }
            $value = $this->$methodName($node);
        } finally {
            array_pop($this->nodeStack);
            --$this->nodeLevel;
        }

        return $value;
    }

    protected function resolveStmtExpression(Expression $node): mixed
    {
        // Just unwrap "expr;" statements.

        return $this->resolve($node->expr);
    }

    protected function resolveNameFullyQualified(Name\FullyQualified $node): string
    {
        return $node->toString();
    }

    private function resolveName(Name $node): string
    {
        if ($node->hasAttribute('resolvedName')) {
            return $node->getAttribute('resolvedName')->toString();
        }

        return $node->toString();
    }

    protected function resolveIdentifier(Node\Identifier $node): string
    {
        return $node->toString();
    }

    /**
     * @throws \Throwable In case of any errors during function evaluation
     */
    protected function resolveExprFuncCall(Expr\FuncCall $node): mixed
    {
        $functionName = $this->resolve($node->name);
        $resolvedArgs = [];
        foreach ($node->args as $argumentNode) {
            $value = $this->resolve($argumentNode->value);
            // if function uses named arguments, then unpack argument name first
            if (isset($argumentNode->name)) {
                $name = $this->resolve($argumentNode->name);
                $resolvedArgs[$name] = $value;
            } else {
                // otherwise simply add argument to the list
                $resolvedArgs[] = $value;
            }
        }

        $reflectedFunction = new \ReflectionFunction($functionName);
        if (!$reflectedFunction->isInternal()) {
            throw new ReflectionException("Only internal PHP functions can be evaluated safely");
        }
        return $reflectedFunction->invoke(...$resolvedArgs);
    }

    protected function resolveScalarFloat(Float_ $node): float
    {
        return $node->value;
    }

    protected function resolveScalarInt(Int_ $node): int
    {
        return $node->value;
    }

    protected function resolveScalarString(String_ $node): string
    {
        return $node->value;
    }

    /**
     * @throws ReflectionException If not in the context of parsing method body
     */
    protected function resolveScalarMagicConstMethod(): string
    {
        if (!$this->context instanceof ReflectionMethod) {
            throw new ReflectionException("Could not resolve __METHOD__ without method context");
        }

        return $this->context->getDeclaringClass()->name . '::' . $this->context->getShortName();
    }

    /**
     * @throws ReflectionException If not in the context of parsing function body
     */
    protected function resolveScalarMagicConstFunction(): string
    {
        if (!$this->context instanceof ReflectionFunctionAbstract) {
            throw new ReflectionException("Could not resolve __FUNCTION__ without function context");
        }

        return $this->context->getName();
    }

    /**
     * @throws ReflectionException If not inside ReflectionFileNamespace or context doesn't have getNamespaceName()
     */
    protected function resolveScalarMagicConstNamespace(): string
    {
        if ($this->context instanceof ReflectionFileNamespace) {
            return $this->context->getName();
        }
        if (!method_exists($this->context, 'getNamespaceName')) {
            throw new ReflectionException("Could not resolve __NAMESPACE__ without having getNamespaceName");
        }

        return $this->context->getNamespaceName();
    }

    /**
     * @throws ReflectionException If not inside ReflectionClass or class children nodes
     */
    protected function resolveScalarMagicConstClass(): string
    {
        if ($this->context instanceof \ReflectionClass) {
            return $this->context->name;
        }
        if (!method_exists($this->context, 'getDeclaringClass')) {
            throw new ReflectionException("Could not resolve __CLASS__ without having getDeclaringClass");
        }
        $declaringClass = $this->context->getDeclaringClass();

        return $declaringClass->name;
    }

    /**
     * @throws ReflectionException If ReflectionContext doesn't have getFileName
     */
    protected function resolveScalarMagicConstDir(): string
    {
        if (!method_exists($this->context, 'getFileName')) {
            throw new ReflectionException("Could not resolve __DIR__ without having getFileName");
        }

        return dirname($this->context->getFileName());
    }

    /**
     * @throws ReflectionException If ReflectionContext doesn't have getFileName
     */
    protected function resolveScalarMagicConstFile(): string
    {
        if (!method_exists($this->context, 'getFileName')) {
            throw new ReflectionException("Could not resolve __FILE__ without having getFileName");
        }

        return $this->context->getFileName();
    }

    protected function resolveScalarMagicConstLine(Line $node): int
    {
        return $node->getStartLine();
    }

    /**
     * @throws ReflectionException If not inside trait context
     */
    protected function resolveScalarMagicConstTrait(): string
    {
        if (!$this->context instanceof \ReflectionClass || !$this->context->isTrait()) {
            throw new ReflectionException("Could not resolve __TRAIT__ without trait context");
        }

        return $this->context->name;
    }

    protected function resolveExprConstFetch(Expr\ConstFetch $node)
    {
        $constantValue = null;
        $isResolved    = false;

        $nodeConstantName = $node->name;
        // If we have resolved type name
        if ($nodeConstantName->hasAttribute('resolvedName')) {
            $nodeConstantName = $nodeConstantName->getAttribute('resolvedName');
        }
        $isFQNConstant = $nodeConstantName instanceof Node\Name\FullyQualified;
        $constantName  = $nodeConstantName->toString();

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

        $isRealConstant = !isset(self::$notConstants[$constantName]);
        if (!$isResolved && defined($constantName)) {
            $constantValue = constant($constantName);
            if (!$isFQNConstant) {
                $constantName  = $this->context->getNamespaceName() . '\\' . $constantName;
            }
        }

        if ($this->nodeLevel === 1 && $isRealConstant) {
            $this->isConstant   = true;
            $this->isConstExpr  = true;
            $this->constantName = $constantName;
        }

        return $constantValue;
    }

    protected function resolveExprClassConstFetch(Expr\ClassConstFetch $node)
    {
        $classToReflectNodeName = $node->class;
        if (!($classToReflectNodeName instanceof Node\Name)) {
            $classToReflectNodeName = $this->resolve($classToReflectNodeName);
            if (!is_string($classToReflectNodeName)) {
                throw new ReflectionException("Unable to resolve class constant.");
            }
            // Strings evaluated as class names are always treated as fully
            // qualified.
            $classToReflectNodeName = new Node\Name\FullyQualified(ltrim($classToReflectNodeName, '\\'));
        }
        // Unwrap resolved class name if we have it inside attributes
        if ($classToReflectNodeName->hasAttribute('resolvedName')) {
            $classToReflectNodeName = $classToReflectNodeName->getAttribute('resolvedName');
        }
        $refClass = $this->fetchReflectionClass($classToReflectNodeName);
        if (($node->name instanceof Expr\Error)) {
            $constantName = '';
        } else {
            $constantName = match (true) {
                $node->name->hasAttribute('resolvedName') => $node->name->getAttribute('resolvedName')->toString(),
                default => $node->name->toString(),
            };
        }

        // special handling of ::class constants
        if ('class' === $constantName) {
            return $refClass->getName();
        }

        $this->isConstant   = true;
        $this->isConstExpr  = true;
        $this->constantName = $classToReflectNodeName . '::' . $constantName;

        return $refClass->getConstant($constantName);
    }

    protected function resolveExprArray(Expr\Array_ $node): array
    {
        // For array expressions we would like to have pretty-printed output too
        $this->isConstExpr = true;

        $result = [];
        foreach ($node->items as $itemIndex => $arrayItem) {
            $itemValue        = $this->resolve($arrayItem->value);
            $itemKey          = isset($arrayItem->key) ? $this->resolve($arrayItem->key) : $itemIndex;
            $result[$itemKey] = $itemValue;
        }

        return $result;
    }

    protected function resolveExprBinaryOpPlus(Expr\BinaryOp\Plus $node): int|float|array
    {
        return $this->resolve($node->left) + $this->resolve($node->right);
    }

    protected function resolveExprBinaryOpMinus(Expr\BinaryOp\Minus $node): int|float
    {
        return $this->resolve($node->left) - $this->resolve($node->right);
    }

    protected function resolveExprBinaryOpMul(Expr\BinaryOp\Mul $node): int|float
    {
        return $this->resolve($node->left) * $this->resolve($node->right);
    }

    protected function resolveExprBinaryOpPow(Expr\BinaryOp\Pow $node): int|float
    {
        return $this->resolve($node->left) ** $this->resolve($node->right);
    }

    protected function resolveExprBinaryOpDiv(Expr\BinaryOp\Div $node): int|float
    {
        return $this->resolve($node->left) / $this->resolve($node->right);
    }

    /**
     * Operands of modulo are converted to int before processing
     *
     * @see https://www.php.net/manual/en/language.operators.arithmetic.php#language.operators.arithmetic
     */
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

    protected function resolveExprTernary(Expr\Ternary $node): mixed
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

    protected function resolveExprUnaryMinus(Expr\UnaryMinus $node): int|float
    {
        return -$this->resolve($node->expr);
    }

    protected function resolveExprUnaryPlus(Expr\UnaryPlus $node): int|float
    {
        return $this->resolve($node->expr);
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
     * @return bool|\ReflectionClass
     *
     * @throws ReflectionException
     */
    private function fetchReflectionClass(Node\Name $node)
    {
        // If we have already resolved node name, we should use it instead
        if ($node->hasAttribute('resolvedName')) {
            $node = $node->getAttribute('resolvedName');
        }
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
                                     ->getParentClass()
                    ;
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
