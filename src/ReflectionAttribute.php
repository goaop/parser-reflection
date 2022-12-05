<?php
/**
 * Parser Reflection API
 *
 * @copyright Copyright 2022, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */
declare(strict_types=1);

namespace Go\ParserReflection;

use Attribute;
use Go\ParserReflection\Traits\InternalPropertiesEmulationTrait;
use Go\ParserReflection\ValueResolver\NodeExpressionResolver;
use PhpParser\Node;
use PhpParser\Node\Stmt\ClassLike;
use ReflectionAttribute as BaseReflectionAttribute;
use Reflector;

/**
 * ReflectionAttribute is a class that represents an attribute.
 *
 * @template T of object
 */
class ReflectionAttribute extends BaseReflectionAttribute
{
    use InternalPropertiesEmulationTrait;

    /**
     * Is repeated
     *
     * @var bool|null
     */
    private ?bool $isRepeated = null;

    /**
     * Initializes reflection instance for given AST-node
     *
     * @param string          $attributeName      Name of the attribute
     * @param ?Node\Attribute $attributeNode      Optional attribute node
     * @param ?ClassLike      $classLikeNode      Class-like node of the attribute class
     * @param ?Reflector      $declaringReflector Reflection on which attribute is declared
     *
     * @throws ReflectionException
     *
     * @noinspection PhpMissingParentConstructorInspection
     */
    public function __construct(
        string                  $attributeName,
        private ?Node\Attribute $attributeNode = null,
        private ?ClassLike      $classLikeNode = null,
        private ?Reflector      $declaringReflector = null
    ) {
        $this->attributeNode ??= ReflectionEngine::parseAttribute($attributeName);
        $this->classLikeNode ??= ReflectionEngine::parseClass($attributeName);
    }

    /**
     * Emulating original behaviour of reflection.
     *
     * Called when invoking {@link var_dump()} on an object
     *
     * @return array
     */
    public function __debugInfo(): array
    {
        return [];
    }

    /**
     * Returns an AST-node for the attribute
     *
     * @return Node\Attribute
     */
    public function getNode(): Node\Attribute
    {
        return $this->attributeNode;
    }

    /**
     * Returns the class-like node of the attribute class
     *
     * @return ClassLike
     */
    public function getClassNode(): ClassLike
    {
        return $this->classLikeNode;
    }

    /**
     * Gets reflection on which attribute is declared
     *
     * @return Reflector|null
     */
    public function getDeclaringReflector(): ?Reflector
    {
        return $this->declaringReflector;
    }

    /**
     * Gets attribute name
     *
     * @return string The name of the attribute parameter.
     */
    public function getName(): string
    {
        return $this->attributeNode->name->toString();
    }

    /**
     * Creates a new instance of the attribute with passed arguments
     *
     * @return T
     */
    public function newInstance(): object
    {
        $attributeClass = $this->getName();
        $arguments      = $this->getArguments();

        return new $attributeClass(...$arguments);
    }

    /**
     * Gets list of passed attribute's arguments.
     *
     * @return array
     */
    public function getArguments(): array
    {
        $arguments = [];
        foreach ($this->getNode()->args as $argument) {
            $expressionSolver = new NodeExpressionResolver($this->declaringReflector);
            $expressionSolver->process($argument);

            if ($identifier = $argument->name) {
                $arguments[$identifier->toString()] = $expressionSolver->getValue();
            } else {
                $arguments[] = $expressionSolver->getValue();
            }
        }
        return $arguments;
    }

    /**
     * Returns the target of the attribute as a bit mask format.
     *
     * @return int
     */
    public function getTarget(): int
    {
        $declaringReflector = $this->getDeclaringReflector();
        $defaultTarget = match(true) {
            $declaringReflector instanceof ReflectionClass => Attribute::TARGET_CLASS,
            $declaringReflector instanceof ReflectionFunction => Attribute::TARGET_FUNCTION,
            $declaringReflector instanceof ReflectionMethod => Attribute::TARGET_METHOD,
            $declaringReflector instanceof ReflectionProperty => Attribute::TARGET_PROPERTY,
            $declaringReflector instanceof ReflectionClassConstant => Attribute::TARGET_CLASS_CONSTANT,
            $declaringReflector instanceof ReflectionParameter => Attribute::TARGET_PARAMETER,
        };

        return $defaultTarget;
    }

    /**
     * Returns {@see true} if the attribute is repeated.
     *
     * @return bool
     */
    public function isRepeated(): bool
    {
        if (!isset($this->isRepeated)) {
            foreach ($this->getClassNode()->attrGroups as $attributeGroup) {
                foreach ($attributeGroup->attrs as $attribute) {
                    foreach ($attribute->args as $argument) {
                        $expressionSolver = new NodeExpressionResolver($this->declaringReflector);
                        $expressionSolver->process($argument);

                        if ($expressionSolver->isConstant()
                            && $attribute->name->toString() === 'Attribute'
                        ) {
                            $this->isRepeated =
                                ($expressionSolver->getValue() & Attribute::IS_REPEATABLE) === Attribute::IS_REPEATABLE;
                            break;
                        }
                    }
                }

                if ($this->isRepeated) {
                    break;
                }
            }

            if (is_null($this->isRepeated)) {
                $this->isRepeated = false;
            }
        }

        return $this->isRepeated;
    }
}
