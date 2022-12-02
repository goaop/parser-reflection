<?php
/**
 * Parser Reflection API
 *
 * @copyright Copyright 2019-2022, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */
declare(strict_types=1);

namespace Go\ParserReflection;

use Go\ParserReflection\Traits\InternalPropertiesEmulationTrait;
use Go\ParserReflection\ValueResolver\NodeExpressionResolver;
use PhpParser\Node\Const_;
use PhpParser\Node\Stmt\ClassConst;
use PhpParser\Node\Stmt\ClassLike;
use Reflection;
use ReflectionClassConstant as BaseReflectionClassConstant;

class ReflectionClassConstant extends BaseReflectionClassConstant
{
    use InternalPropertiesEmulationTrait;

    /**
     * Concrete class constant node
     *
     * @var ClassConst
     */
    private mixed $classConstantNode;

    /**
     * @var Const_
     */
    private mixed $constNode;

    /**
     * Name of the class
     *
     * @var string
     */
    private string $className;

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
     * @param string      $className         Name of the class
     * @param string      $classConstantName Name of the class constant to reflect
     * @param ?ClassConst $classConstNode    ClassConstant definition node
     * @param Const_|null $constNode         Concrete const definition node
     *
     * @noinspection PhpMissingParentConstructorInspection*/
    public function __construct(
        string $className,
        string $classConstantName,
        ClassConst $classConstNode = null,
        Const_ $constNode = null
    ) {
        $this->className = ltrim($className, '\\');

        if (!$classConstNode || !$constNode) {
            [$classConstNode, $constNode] = ReflectionEngine::parseClassConstant($className, $classConstantName);
        }
        // Let's unset original read-only property to have a control over it via __get
        unset($this->name, $this->class);

        $this->classConstantNode = $classConstNode;
        $this->constNode = $constNode;
    }

    /**
     * Emulating original behaviour of reflection.
     *
     * Called when invoking {@link var_dump()} on an object
     *
     * @return array{name: string, class: class-string}
     */
    public function __debugInfo(): array
    {
        return [
            'name' => $this->getName(),
            'class' => $this->className
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function getDeclaringClass(): ReflectionClass
    {
        return new ReflectionClass($this->className);
    }

    /**
     * {@inheritDoc}
     */
    public function getDocComment(): string|false
    {
        $docBlock = $this->classConstantNode->getDocComment();

        return $docBlock ? $docBlock->getText() : false;
    }

    /**
     * {@inheritDoc}
     */
    public function getModifiers(): int
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
     * {@inheritDoc}
     */
    public function getName(): string
    {
        return $this->constNode->name->toString();
    }

    /**
     * {@inheritDoc}
     */
    public function getValue(): mixed
    {
        $solver = new NodeExpressionResolver($this->getDeclaringClass());
        $solver->process($this->constNode->value);
        return $solver->getValue();
    }

    /**
     * {@inheritDoc}
     */
    public function isPrivate(): bool
    {
        return $this->classConstantNode->isPrivate();
    }

    /**
     * {@inheritDoc}
     */
    public function isProtected(): bool
    {
        return $this->classConstantNode->isProtected();
    }

    /**
     * {@inheritDoc}
     */
    public function isPublic(): bool
    {
        return $this->classConstantNode->isPublic();
    }

    /**
     * {@inheritDoc}
     */
    public function getAttributes(?string $name = null, int $flags = 0): array
    {
        return parent::getAttributes($name, $flags);
    }

    /**
     * {@inheritDoc}
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
        if (PHP_VERSION_ID >= 70300 && isset($typeMap[$type])) {
            $type = $typeMap[$type];
        }
        $valueType = new ReflectionType($type, false, true);

        return sprintf(
            "Constant [ %s %s %s ] { %s }\n",
            implode(' ', Reflection::getModifierNames($this->getModifiers())),
            strtolower(ReflectionType::convertToDisplayType($valueType)),
            $this->getName(),
            $value
        );
    }
}
