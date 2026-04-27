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
use PhpParser\Node;
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
     *
     * @var array<string, bool>
     */
    private static array $notConstants = [
        'true'  => true,
        'false' => true,
        'null'  => true,
    ];

    /**
     * Current reflection context for parsing
     *
     * @var \ReflectionClass<object>|\ReflectionFunction|\ReflectionMethod|\ReflectionClassConstant|\ReflectionParameter|\ReflectionAttribute<object>|\ReflectionProperty|ReflectionFileNamespace|null
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

    /**
     * @param \ReflectionClass<object>|\ReflectionFunction|\ReflectionMethod|\ReflectionClassConstant|\ReflectionParameter|\ReflectionAttribute<object>|\ReflectionProperty|ReflectionFileNamespace|null $context
     */
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
                    $resolvedName = $constantNodeName->getAttribute('resolvedName');
                    if ($resolvedName instanceof Name) {
                        $constantNodeName = $resolvedName;
                    }
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
            if ($node instanceof Expr) {
                $printer    = new Standard(['shortArraySyntax' => true]);
                $expression = $printer->prettyPrintExpr($node);
            }
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
            $resolvedName = $node->getAttribute('resolvedName');
            if ($resolvedName instanceof Name) {
                return $resolvedName->toString();
            }
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
            if (!$argumentNode instanceof Node\Arg) {
                throw new ReflectionException('Cannot statically resolve a variadic placeholder argument in a function call');
            }
            $value = $this->resolve($argumentNode->value);
            // if function uses named arguments, then unpack argument name first
            if (isset($argumentNode->name)) {
                $name = $this->resolve($argumentNode->name);
                if (!is_string($name) && !is_int($name)) {
                    throw new ReflectionException(sprintf('Named argument key must be string or int, got %s', gettype($name)));
                }
                $resolvedArgs[$name] = $value;
            } else {
                // otherwise simply add argument to the list
                $resolvedArgs[] = $value;
            }
        }

        if (!is_string($functionName) && !($functionName instanceof \Closure)) {
            throw new ReflectionException("Could not resolve function name for function call.");
        }
        $reflectedFunction = new \ReflectionFunction($functionName);
        if (!$reflectedFunction->isInternal()) {
            throw new ReflectionException("Only internal PHP functions can be evaluated safely");
        }
        return $reflectedFunction->invoke(...$resolvedArgs);
    }

    /**
     * Resolves new expression by instantiating the class with constructor arguments
     *
     * @throws \Throwable In case of any errors during class instantiation
     */
    protected function resolveExprNew(Expr\New_ $node): object
    {
        $classToInstantiateNode = $node->class;
        
        // Resolve class name - it can be a Name node or an expression
        if ($classToInstantiateNode instanceof Node\Name) {
            // Unwrap resolved class name if we have it inside attributes
            if ($classToInstantiateNode->hasAttribute('resolvedName')) {
                $resolvedName = $classToInstantiateNode->getAttribute('resolvedName');
                if ($resolvedName instanceof Node\Name) {
                    $classToInstantiateNode = $resolvedName;
                }
            }
            $className = $classToInstantiateNode->toString();
        } else {
            // It's an expression, resolve it to get class name
            $className = $this->resolve($classToInstantiateNode);
            if (!is_string($className)) {
                throw new ReflectionException("Unable to resolve class name for instantiation.");
            }
        }

        // Resolve constructor arguments
        $resolvedArgs = [];
        foreach ($node->args as $argumentNode) {
            if (!$argumentNode instanceof Node\Arg) {
                throw new ReflectionException('Cannot statically resolve a variadic placeholder argument in a constructor call');
            }
            $value = $this->resolve($argumentNode->value);
            // if constructor uses named arguments, then unpack argument name first
            if (isset($argumentNode->name)) {
                $name = $this->resolve($argumentNode->name);
                if (!is_string($name) && !is_int($name)) {
                    throw new ReflectionException(sprintf('Named argument key must be string or int, got %s', gettype($name)));
                }
                $resolvedArgs[$name] = $value;
            } else {
                // otherwise simply add argument to the list
                $resolvedArgs[] = $value;
            }
        }

        // Use ReflectionClass to safely instantiate the class
        if (!class_exists($className)) {
            throw new ReflectionException("Class '{$className}' does not exist and cannot be instantiated.");
        }
        $reflectionClass = new \ReflectionClass($className);
        return $reflectionClass->newInstance(...$resolvedArgs);
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
        if (!($this->context instanceof \ReflectionClass
            || $this->context instanceof \ReflectionFunction
            || $this->context instanceof \ReflectionMethod
        )) {
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
        if ($this->context === null || !method_exists($this->context, 'getDeclaringClass')) {
            throw new ReflectionException("Could not resolve __CLASS__ without having getDeclaringClass");
        }
        $declaringClass = $this->context->getDeclaringClass();
        if (!$declaringClass instanceof \ReflectionClass) {
            throw new ReflectionException("Could not resolve __CLASS__: getDeclaringClass() did not return a ReflectionClass");
        }

        return $declaringClass->name;
    }

    /**
     * @throws ReflectionException If ReflectionContext doesn't have getFileName
     */
    protected function resolveScalarMagicConstDir(): string
    {
        if (!($this->context instanceof \ReflectionClass
            || $this->context instanceof \ReflectionFunction
            || $this->context instanceof \ReflectionMethod
            || $this->context instanceof ReflectionFileNamespace
        )) {
            throw new ReflectionException("Could not resolve __DIR__ without having getFileName");
        }
        $fileName = $this->context->getFileName();

        return dirname((string) $fileName);
    }

    /**
     * @throws ReflectionException If ReflectionContext doesn't have getFileName
     */
    protected function resolveScalarMagicConstFile(): string
    {
        if (!($this->context instanceof \ReflectionClass
            || $this->context instanceof \ReflectionFunction
            || $this->context instanceof \ReflectionMethod
            || $this->context instanceof ReflectionFileNamespace
        )) {
            throw new ReflectionException("Could not resolve __FILE__ without having getFileName");
        }

        return (string) $this->context->getFileName();
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

    protected function resolveExprConstFetch(Expr\ConstFetch $node): mixed
    {
        $constantValue = null;
        $isResolved    = false;

        $nodeConstantName = $node->name;
        // If we have resolved type name
        if ($nodeConstantName->hasAttribute('resolvedName')) {
            $resolvedConstantName = $nodeConstantName->getAttribute('resolvedName');
            if ($resolvedConstantName instanceof Name) {
                $nodeConstantName = $resolvedConstantName;
            }
        }
        $isFQNConstant = $nodeConstantName instanceof Node\Name\FullyQualified;
        $constantName  = $nodeConstantName->toString();

        if (!$isFQNConstant && ($this->context instanceof \ReflectionClass
            || $this->context instanceof \ReflectionFunction
            || $this->context instanceof \ReflectionMethod
            || $this->context instanceof ReflectionFileNamespace
        )) {
            $fileName      = (string) $this->context->getFileName();
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
            if (!$isFQNConstant && ($this->context instanceof \ReflectionClass
                || $this->context instanceof \ReflectionFunction
                || $this->context instanceof \ReflectionMethod
            )) {
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

    protected function resolveExprClassConstFetch(Expr\ClassConstFetch $node): mixed
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
            $resolvedClassName = $classToReflectNodeName->getAttribute('resolvedName');
            if ($resolvedClassName instanceof Node\Name) {
                $classToReflectNodeName = $resolvedClassName;
            }
        }
        $refClass = $this->fetchReflectionClass($classToReflectNodeName);
        if ($refClass === false) {
            throw new ReflectionException("Could not resolve class for class constant fetch.");
        }
        if ($node->name instanceof Expr\Error) {
            $constantName = '';
        } elseif ($node->name instanceof Node\Identifier) {
            if ($node->name->hasAttribute('resolvedName')) {
                $resolvedNodeName = $node->name->getAttribute('resolvedName');
                $constantName = $resolvedNodeName instanceof Node\Name ? $resolvedNodeName->toString() : $node->name->toString();
            } else {
                $constantName = $node->name->toString();
            }
        } else {
            $resolvedName = $this->resolve($node->name);
            $constantName = is_string($resolvedName) ? $resolvedName : '';
        }

        // special handling of ::class constants
        if ('class' === $constantName) {
            return $refClass->getName();
        }

        $this->isConstant   = true;
        $this->isConstExpr  = true;
        $this->constantName = $classToReflectNodeName->toString() . '::' . $constantName;

        return $refClass->getConstant($constantName);
    }

    /**
     * Resolves property fetch on an object, e.g. SomeEnum::CASE->value
     */
    protected function resolveExprPropertyFetch(Expr\PropertyFetch $node): mixed
    {
        $object = $this->resolve($node->var);
        if (!is_object($object)) {
            throw new ReflectionException("Property fetch requires an object, got " . gettype($object));
        }

        if ($node->name instanceof Node\Identifier) {
            $propertyName = $node->name->toString();
        } else {
            $resolvedName = $this->resolve($node->name);
            if (!is_string($resolvedName)) {
                throw new ReflectionException("Could not resolve property name for property fetch.");
            }
            $propertyName = $resolvedName;
        }

        if (!property_exists($object, $propertyName)) {
            throw new ReflectionException(sprintf("Property '%s' does not exist on object of type %s", $propertyName, get_class($object)));
        }

        $this->isConstant   = false;
        $this->constantName = null;
        $this->isConstExpr  = true;

        return $object->$propertyName;
    }

    /**
     * @return array<int|string, mixed>
     */
    protected function resolveExprArray(Expr\Array_ $node): array
    {
        // For array expressions we would like to have pretty-printed output too
        $this->isConstExpr = true;

        $result = [];
        foreach ($node->items as $itemIndex => $arrayItem) {
            $itemValue = $this->resolve($arrayItem->value);
            if (isset($arrayItem->key)) {
                $itemKey = $this->resolve($arrayItem->key);
                if (!is_string($itemKey) && !is_int($itemKey)) {
                    throw new ReflectionException(sprintf('Array key must be string or int, got %s', gettype($itemKey)));
                }
            } else {
                $itemKey = $itemIndex;
            }
            $result[$itemKey] = $itemValue;
        }

        return $result;
    }

    /**
     * @return int|float|array<int|string, mixed>
     */
    protected function resolveExprBinaryOpPlus(Expr\BinaryOp\Plus $node): int|float|array
    {
        $left  = $this->resolve($node->left);
        $right = $this->resolve($node->right);
        if (is_array($left) && is_array($right)) {
            return $left + $right;
        }

        return $this->resolveNumeric($left) + $this->resolveNumeric($right);
    }

    protected function resolveExprBinaryOpMinus(Expr\BinaryOp\Minus $node): int|float
    {
        return $this->resolveNumeric($this->resolve($node->left)) - $this->resolveNumeric($this->resolve($node->right));
    }

    protected function resolveExprBinaryOpMul(Expr\BinaryOp\Mul $node): int|float
    {
        return $this->resolveNumeric($this->resolve($node->left)) * $this->resolveNumeric($this->resolve($node->right));
    }

    protected function resolveExprBinaryOpPow(Expr\BinaryOp\Pow $node): int|float
    {
        return $this->resolveNumeric($this->resolve($node->left)) ** $this->resolveNumeric($this->resolve($node->right));
    }

    protected function resolveExprBinaryOpDiv(Expr\BinaryOp\Div $node): int|float
    {
        return $this->resolveNumeric($this->resolve($node->left)) / $this->resolveNumeric($this->resolve($node->right));
    }

    /**
     * Operands of modulo are converted to int before processing
     *
     * @see https://www.php.net/manual/en/language.operators.arithmetic.php#language.operators.arithmetic
     */
    protected function resolveExprBinaryOpMod(Expr\BinaryOp\Mod $node): int
    {
        return $this->resolveInt($this->resolve($node->left)) % $this->resolveInt($this->resolve($node->right));
    }

    protected function resolveExprBooleanNot(Expr\BooleanNot $node): bool
    {
        return !$this->resolve($node->expr);
    }

    protected function resolveExprBitwiseNot(Expr\BitwiseNot $node): int|string
    {
        $value = $this->resolve($node->expr);
        if (is_string($value)) {
            return ~$value;
        }

        return ~$this->resolveInt($value);
    }

    protected function resolveExprBinaryOpBitwiseOr(Expr\BinaryOp\BitwiseOr $node): int
    {
        return $this->resolveInt($this->resolve($node->left)) | $this->resolveInt($this->resolve($node->right));
    }

    protected function resolveExprBinaryOpBitwiseAnd(Expr\BinaryOp\BitwiseAnd $node): int
    {
        return $this->resolveInt($this->resolve($node->left)) & $this->resolveInt($this->resolve($node->right));
    }

    protected function resolveExprBinaryOpBitwiseXor(Expr\BinaryOp\BitwiseXor $node): int
    {
        return $this->resolveInt($this->resolve($node->left)) ^ $this->resolveInt($this->resolve($node->right));
    }

    protected function resolveExprBinaryOpShiftLeft(Expr\BinaryOp\ShiftLeft $node): int
    {
        return $this->resolveInt($this->resolve($node->left)) << $this->resolveInt($this->resolve($node->right));
    }

    protected function resolveExprBinaryOpShiftRight(Expr\BinaryOp\ShiftRight $node): int
    {
        return $this->resolveInt($this->resolve($node->left)) >> $this->resolveInt($this->resolve($node->right));
    }

    protected function resolveExprBinaryOpConcat(Expr\BinaryOp\Concat $node): string
    {
        $left  = $this->resolve($node->left);
        $right = $this->resolve($node->right);

        return (is_scalar($left) || $left === null ? (string) $left : '') . (is_scalar($right) || $right === null ? (string) $right : '');
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
        return -$this->resolveNumeric($this->resolve($node->expr));
    }

    protected function resolveExprUnaryPlus(Expr\UnaryPlus $node): int|float
    {
        return $this->resolveNumeric($this->resolve($node->expr));
    }

    private function resolveNumeric(mixed $value): int|float
    {
        if (is_int($value) || is_float($value)) {
            return $value;
        }
        if (is_string($value) && is_numeric($value)) {
            return strpos($value, '.') !== false ? (float) $value : (int) $value;
        }
        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        return 0;
    }

    private function resolveInt(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_float($value) || is_string($value) || is_bool($value)) {
            return (int) $value;
        }

        return 0;
    }

    private function getDispatchMethodFor(Node $node): string
    {
        $nodeType = $node->getType();

        return 'resolve' . str_replace('_', '', $nodeType);
    }

    /**
     * Returns the \ReflectionClass for the current context, if available.
     *
     * @return \ReflectionClass<object>|null
     */
    private function getContextClass(): ?\ReflectionClass
    {
        if ($this->context instanceof \ReflectionClass) {
            return $this->context;
        }

        if ($this->context instanceof \ReflectionMethod
            || $this->context instanceof \ReflectionProperty
            || $this->context instanceof \ReflectionClassConstant
        ) {
            return $this->context->getDeclaringClass();
        }

        if ($this->context instanceof \ReflectionParameter) {
            return $this->context->getDeclaringClass();
        }

        return null;
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
     * @return \ReflectionClass<object>|false
     *
     * @throws ReflectionException
     */
    private function fetchReflectionClass(Node\Name $node)
    {
        // If we have already resolved node name, we should use it instead
        if ($node->hasAttribute('resolvedName')) {
            $resolvedNode = $node->getAttribute('resolvedName');
            if ($resolvedNode instanceof Node\Name) {
                $node = $resolvedNode;
            }
        }
        $className  = $node->toString();
        $isFQNClass = $node instanceof Node\Name\FullyQualified;
        if ($isFQNClass) {
            // check to see if the class is already loaded and is safe to use
            // PHP's ReflectionClass to determine if the class is user defined
            if (class_exists($className, false)) {
                $refClass = new \ReflectionClass($className);
                if (!$refClass->isUserDefined() || $refClass->isEnum()) {
                    return $refClass;
                }
            }

            // Return the context class directly if it matches the requested class name,
            // to avoid infinite recursion when a class constant references its own class by name
            // (e.g. const RELATIVE = SomeClass::LITERAL where SomeClass is the class being reflected)
            $contextClass = $this->getContextClass();
            if ($contextClass !== null && $contextClass->getName() === $className) {
                return $contextClass;
            }

            return new ReflectionClass($className);
        }

        if ('self' === $className) {
            if ($this->context instanceof \ReflectionClass) {
                return $this->context;
            }

            if ($this->context instanceof \ReflectionMethod
                || $this->context instanceof \ReflectionProperty
                || $this->context instanceof \ReflectionClassConstant
            ) {
                return $this->context->getDeclaringClass();
            }

            if ($this->context instanceof \ReflectionParameter) {
                $declaringClass = $this->context->getDeclaringClass();

                return $declaringClass ?? false;
            }
        }

        if ('parent' === $className) {
            if ($this->context instanceof \ReflectionClass) {
                return $this->context->getParentClass();
            }

            if ($this->context instanceof \ReflectionMethod
                || $this->context instanceof \ReflectionProperty
                || $this->context instanceof \ReflectionClassConstant
            ) {
                return $this->context->getDeclaringClass()->getParentClass();
            }

            if ($this->context instanceof \ReflectionParameter) {
                $declaringClass = $this->context->getDeclaringClass();

                return $declaringClass !== null ? $declaringClass->getParentClass() : false;
            }
        }

        if ($this->context instanceof \ReflectionClass
            || $this->context instanceof \ReflectionFunction
            || $this->context instanceof \ReflectionMethod
            || $this->context instanceof ReflectionFileNamespace
        ) {
            $fileName      = (string) $this->context->getFileName();
            $namespaceName = $this->resolveScalarMagicConstNamespace();

            $fileNamespace = new ReflectionFileNamespace($fileName, $namespaceName);

            return $fileNamespace->getClass($className);
        }

        throw new ReflectionException("Can not resolve class $className");
    }
}
