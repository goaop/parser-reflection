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

use Go\ParserReflection\Traits\AttributeResolverTrait;
use Go\ParserReflection\Traits\InternalPropertiesEmulationTrait;
use Go\ParserReflection\ValueResolver\NodeExpressionResolver;
use PhpParser\Node;
use PhpParser\Node\Const_;
use PhpParser\Node\Stmt\ClassConst;
use PhpParser\Node\Stmt\ClassLike;
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
    private ClassConst $classConstantNode;

    private Const_ $constNode;

    private string $className;

    private mixed $value;

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
                    $classConstants[$classConstName] = new ReflectionClassConstant(
                        $reflectionClassFQN,
                        $classConstName,
                        $classLevelNode,
                        $const
                    );
                }
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
        ?ClassConst $classConstNode = null,
        ?Const_ $constNode = null
    ) {
        $this->className = ltrim($className, '\\');

        if (!$classConstNode || !$constNode) {
            [$classConstNode, $constNode] = ReflectionEngine::parseClassConstant($className, $classConstantName);
        }
        // Let's unset original read-only property to have a control over it via __get
        unset($this->name, $this->class);

        $this->classConstantNode = $classConstNode;
        $this->constNode = $constNode;

        $expressionSolver = new NodeExpressionResolver($this->getDeclaringClass());
        $expressionSolver->process($this->constNode->value);

        $this->value = $expressionSolver->getValue();
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
        $docBlock = $this->classConstantNode->getDocComment();

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
        return $this->constNode->name->toString();
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
    public function isPrivate(): bool
    {
        return $this->classConstantNode->isPrivate();
    }

    /**
     * @inheritDoc
     */
    public function isProtected(): bool
    {
        return $this->classConstantNode->isProtected();
    }

    /**
     * @inheritDoc
     */
    public function isPublic(): bool
    {
        return $this->classConstantNode->isPublic();
    }

    /**
     * @inheritDoc
     */
    public function isFinal(): bool
    {
        return $this->classConstantNode->isFinal();
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
        $value = $this->getValue();
        $type  = gettype($value);
        if (isset($typeMap[$type])) {
            $type = $typeMap[$type];
        }
        $valueType = new ReflectionType($type, false, true);

        return sprintf(
            "Constant [ %s %s %s ] { %s }\n",
            implode(' ', Reflection::getModifierNames($this->getModifiers())),
            strtolower((string) ReflectionType::convertToDisplayType($valueType)),
            $this->getName(),
            (string) $value
        );
    }

    public function getNode(): Node\Stmt\ClassConst
    {
        return $this->classConstantNode;
    }
}
