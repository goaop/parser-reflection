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

use Go\ParserReflection\ValueResolver\NodeExpressionResolver;
use PhpParser\Node\Const_;
use PhpParser\Node\Stmt\ClassConst;
use PhpParser\Node\Stmt\ClassLike;
use \ReflectionClassConstant as BaseReflectionClassConstant;

class ReflectionClassConstant extends BaseReflectionClassConstant
{
    /**
     * Concrete class constant node
     *
     * @var ClassConst
     */
    private $classConstantNode;

    /**
     * @var Const_
     */
    private $constNode;

    /**
     * Name of the class
     *
     * @var string
     */
    private $className;

    /**
     * Parses class constants from the concrete class node
     *
     * @param ClassLike $classLikeNode Class-like node
     * @param string $reflectionClassName FQN of the class
     *
     * @return array|ReflectionClassConstant[]
     */
    public static function collectFromClassNode(ClassLike $classLikeNode, string $reflectionClassName): array
    {
        $classConstants = [];

        foreach ($classLikeNode->stmts as $classLevelNode) {
            if ($classLevelNode instanceof ClassConst) {
                foreach ($classLevelNode->consts as $const) {
                    $classConstName = $const->name->toString();
                    $classConstants[$classConstName] = new ReflectionClassConstant(
                        $reflectionClassName,
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
     *
     * @param string $className Name of the class
     * @param string $classConstantName Name of the class constant to reflect
     * @param ClassConst $classConstNode ClassConstant definition node
     * @param Const_|null $constNode Concrete const definition node
     */
    public function __construct(
        string $className,
        string $classConstantName,
        ClassConst $classConstNode = null,
        Const_ $constNode = null
    ) {
        $this->className = ltrim($className, '\\');

        if (!$classConstNode || !$constNode) {
            list($classConstNode, $constNode) = ReflectionEngine::parseClassConstant($className, $classConstantName);
        }

        $this->classConstantNode = $classConstNode;
        $this->constNode = $constNode;
    }

    /**
     * @inheritDoc
     */
    public function getDeclaringClass()
    {
        return new ReflectionClass($this->className);
    }

    /**
     * @inheritDoc
     */
    public function getDocComment()
    {
        $docBlock = $this->classConstantNode->getDocComment();

        return $docBlock ? $docBlock->getText() : false;
    }

    /**
     * @inheritDoc
     */
    public function getModifiers()
    {
        $modifiers = 0;
        if ($this->isPublic()) {
            $modifiers += ReflectionMethod::IS_PUBLIC;
        }
        if ($this->isProtected()) {
            $modifiers += ReflectionMethod::IS_PROTECTED;
        }
        if ($this->isPrivate()) {
            $modifiers += ReflectionMethod::IS_PRIVATE;
        }

        return $modifiers;
    }

    /**
     * @inheritDoc
     */
    public function getName()
    {
        return $this->constNode->name->toString();
    }

    /**
     * @inheritDoc
     */
    public function getValue()
    {
        $solver = new NodeExpressionResolver($this->getDeclaringClass());
        $solver->process($this->constNode->value);
        return $solver->getValue();
    }

    /**
     * @inheritDoc
     */
    public function isPrivate()
    {
        return $this->classConstantNode->isPrivate();
    }

    /**
     * @inheritDoc
     */
    public function isProtected()
    {
        return $this->classConstantNode->isProtected();
    }

    /**
     * @inheritDoc
     */
    public function isPublic()
    {
        return $this->classConstantNode->isPublic();
    }

    /**
     * @inheritDoc
     */
    public function __toString()
    {
        $value = $this->getValue();
        $valueType = new ReflectionType(gettype($value), null, true);

        return sprintf(
            "Constant [ %s %s %s ] { %s }\n",
            implode(' ', \Reflection::getModifierNames($this->getModifiers())),
            strtolower((string) ReflectionType::convertToDisplayType($valueType)),
            $this->getName(),
            (string) $value
        );
    }
}
