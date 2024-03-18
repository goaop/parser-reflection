<?php
/**
 * Parser Reflection API
 *
 * @copyright Copyright 2019, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */
declare(strict_types=1);

namespace Go\ParserReflection;

use Go\ParserReflection\Resolver\TypeExpressionResolver;
use Go\ParserReflection\Traits\AttributeResolverTrait;
use Go\ParserReflection\Traits\InternalPropertiesEmulationTrait;
use Go\ParserReflection\Resolver\NodeExpressionResolver;
use PhpParser\Node;
use PhpParser\Node\Const_;
use PhpParser\Node\Stmt\ClassConst;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\EnumCase;
use Reflection;
use ReflectionClassConstant as BaseReflectionClassConstant;

/**
 * @see \Go\ParserReflection\ReflectionClassConstantTest
 */
class ReflectionClassConstant extends BaseReflectionClassConstant
{
    use InternalPropertiesEmulationTrait;
    use AttributeResolverTrait;

    /**
     * Concrete class constant node
     */
    private ClassConst|EnumCase $classConstOrEnumCaseNode;

    private Const_|EnumCase $constOrEnumCaseNode;

    private string $className;

    private mixed $value = null;

    private \ReflectionUnionType|\ReflectionNamedType|\ReflectionIntersectionType|null $type = null;

    /**
     * Parses class constants from the concrete class node
     *
     * @return ReflectionClassConstant[]
     */
    public static function collectFromClassNode(ClassLike $classLikeNode, string $reflectionClassFQN): array
    {
        $classConstants = [];

        foreach ($classLikeNode->stmts as $classLevelNode) {
            if ($classLevelNode instanceof ClassConst) {
                foreach ($classLevelNode->consts as $const) {
                    $classConstName = $const->name->toString();
                    $classConstants[$classConstName] = new static(
                        $reflectionClassFQN,
                        $classConstName,
                        $classLevelNode,
                        $const
                    );
                }
            }
            // Enum cases are reported as constants too
            if ($classLevelNode instanceof Node\Stmt\EnumCase) {
                $enumCaseName = $classLevelNode->name->toString();
                $classConstants[$enumCaseName] = new static (
                    $reflectionClassFQN,
                    $enumCaseName,
                    $classLevelNode,
                    $classLevelNode
                );
            }
        }

        return $classConstants;
    }

    /**
     * Initializes a reflection for the class constant
     */
    public function __construct(
        string $className,
        string $classConstantName,
        ClassConst|EnumCase|null $classConstNode = null,
        Const_|EnumCase|null $constNode = null
    ) {
        $this->className = ltrim($className, '\\');

        if (!$classConstNode) {
            [$classConstNode, $constNode] = ReflectionEngine::parseClassConstant($className, $classConstantName);
        }
        // Let's unset original read-only property to have a control over it via __get
        unset($this->name, $this->class);

        $this->classConstOrEnumCaseNode = $classConstNode;
        $this->constOrEnumCaseNode = $constNode;

        $expressionSolver = new NodeExpressionResolver($this->getDeclaringClass());

        // We can statically resolve value only fot ClassConst, as for EnumCase we need to have object itself as default
        if ($classConstNode instanceof ClassConst) {
            $expressionSolver->process($this->constOrEnumCaseNode->value);
            $this->value = $expressionSolver->getValue();
        }

        if ($this->hasType()) {
            // If we have null value, this handled internally as nullable type too
            $hasDefaultNull = $this->getValue() === null;

            $typeResolver = new TypeExpressionResolver($this->getDeclaringClass());
            $typeResolver->process($this->classConstOrEnumCaseNode->type, $hasDefaultNull);

            $this->type = $typeResolver->getType();
        }
    }

    /**
     * Emulating original behaviour of reflection
     */
    public function __debugInfo(): array
    {
        return [
            'name' => $this->getName(),
            'class' => $this->className
        ];
    }

    /**
     * @inheritDoc
     */
    public function getDeclaringClass(): \ReflectionClass
    {
        return new ReflectionClass($this->className);
    }

    /**
     * @inheritDoc
     */
    public function getDocComment(): string|false
    {
        $docBlock = $this->classConstOrEnumCaseNode->getDocComment();

        return $docBlock ? $docBlock->getText() : false;
    }

    /**
     * @inheritDoc
     */
    public function getModifiers(): int
    {
        $modifiers = 0;
        if ($this->isPublic()) {
            $modifiers += BaseReflectionClassConstant::IS_PUBLIC;
        }
        if ($this->isProtected()) {
            $modifiers += BaseReflectionClassConstant::IS_PROTECTED;
        }
        if ($this->isPrivate()) {
            $modifiers += BaseReflectionClassConstant::IS_PRIVATE;
        }
        if ($this->isFinal()) {
            $modifiers += BaseReflectionClassConstant::IS_FINAL;
        }

        return $modifiers;
    }

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return $this->constOrEnumCaseNode->name->toString();
    }

    /**
     * @inheritDoc
     */
    public function getValue(): mixed
    {
        return $this->value;
    }

    /**
     * @inheritDoc
     */
    public function isEnumCase(): bool
    {
        return $this->classConstOrEnumCaseNode instanceof EnumCase;
    }

    /**
     * @inheritDoc
     */
    public function isPrivate(): bool
    {
        return $this->classConstOrEnumCaseNode instanceof ClassConst && $this->classConstOrEnumCaseNode->isPrivate();
    }

    /**
     * @inheritDoc
     */
    public function isProtected(): bool
    {
        return $this->classConstOrEnumCaseNode instanceof ClassConst && $this->classConstOrEnumCaseNode->isProtected();
    }

    /**
     * @inheritDoc
     */
    public function isPublic(): bool
    {
        $isPublicClassConst = $this->classConstOrEnumCaseNode instanceof ClassConst && $this->classConstOrEnumCaseNode->isPublic();
        $isEnumCase         = $this->classConstOrEnumCaseNode instanceof EnumCase;

        return $isPublicClassConst || $isEnumCase;
    }

    /**
     * @inheritDoc
     */
    public function isFinal(): bool
    {
        return $this->classConstOrEnumCaseNode instanceof ClassConst && $this->classConstOrEnumCaseNode->isFinal();
    }

    /**
     * @inheritDoc
     */
    public function hasType(): bool
    {
        return $this->classConstOrEnumCaseNode instanceof ClassConst && isset($this->classConstOrEnumCaseNode->type);
    }

    public function getType(): ?\ReflectionType
    {
        return $this->type;
    }

    /**
     * @inheritDoc
     */
    public function __toString(): string
    {
        # Starting from PHP7.3 gettype returns different names, need to remap them
        static $typeMap = [
            'integer' => 'int',
            'boolean' => 'bool',
            'double'  => 'float',
        ];
        $value = $this->isEnumCase() ? 'Object' : $this->getValue();
        if (!$this->hasType()) {
            $type  = gettype($value);
            if (isset($typeMap[$type])) {
                $type = $typeMap[$type];
            }
            $type = strtolower($type);

            if ($this->isEnumCase()) {
                $type = $this->className;
            }
            $valueType = new ReflectionType($type, false);
        } else {
            $valueType = $this->type;
        }

        return sprintf(
            "Constant [ %s %s %s ] { %s }\n",
            implode(' ', Reflection::getModifierNames($this->getModifiers())),
            ReflectionType::convertToDisplayType($valueType),
            $this->getName(),
            is_object($value) ? 'Object' : $value
        );
    }

    public function getNode(): ClassConst|EnumCase
    {
        return $this->classConstOrEnumCaseNode;
    }
}
