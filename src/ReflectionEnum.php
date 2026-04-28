<?php

declare(strict_types=1);
/**
 * Parser Reflection API
 *
 * @copyright Copyright 2024, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\ParserReflection;

use Go\ParserReflection\Traits\AttributeResolverTrait;
use Go\ParserReflection\Traits\InternalPropertiesEmulationTrait;
use Go\ParserReflection\Traits\ReflectionClassLikeTrait;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Enum_;
use PhpParser\Node\Stmt\EnumCase;
use ReflectionEnum as InternalReflectionEnum;

/**
 * AST-based reflection for enums
 *
 * @see \Go\ParserReflection\ReflectionEnumTest
 * @extends InternalReflectionEnum<\UnitEnum>
 */
final class ReflectionEnum extends InternalReflectionEnum
{
    use InternalPropertiesEmulationTrait;
    use ReflectionClassLikeTrait;
    use AttributeResolverTrait;

    /**
     * Re-declare to remove parent's @readonly / PHP 8.4 hook so it can be unset in constructor
     */
    public string $name;

    /**
     * Initializes reflection instance for an enum
     *
     * @param object|string $argument      Enum class name or instance
     * @param ?ClassLike    $classLikeNode AST node for enum
     */
    public function __construct(object|string $argument, ?ClassLike $classLikeNode = null)
    {
        $fullClassName = is_object($argument) ? get_class($argument) : ltrim($argument, '\\');
        $namespaceParts = explode('\\', $fullClassName);
        $shortName = array_pop($namespaceParts);
        if ($shortName !== '') {
            $this->className = $shortName;
        } else {
            $this->className = $fullClassName !== '' ? $fullClassName : 'UnknownEnum';
        }
        // Let's unset original read-only property to have a control over it via __get
        unset($this->name);

        $this->namespaceName = implode('\\', $namespaceParts);

        $this->classLikeNode = $classLikeNode ?: ReflectionEngine::parseClass($fullClassName);
    }

    /**
     * {@inheritDoc}
     */
    public function getBackingType(): ?ReflectionNamedType
    {
        $enumNode = $this->getEnumNode();
        if ($enumNode->scalarType === null) {
            return null;
        }

        $typeName = $enumNode->scalarType->toString();

        return new ReflectionNamedType($typeName, false, true);
    }

    /**
     * {@inheritDoc}
     */
    public function getCase(string $name): ReflectionEnumUnitCase|ReflectionEnumBackedCase
    {
        $enumNode = $this->getEnumNode();
        $fullClassName = $this->getName();

        foreach ($enumNode->stmts as $stmt) {
            if ($stmt instanceof EnumCase && $stmt->name->toString() === $name) {
                return $this->createEnumCaseReflection($fullClassName, $stmt);
            }
        }

        throw new ReflectionException("Case $name does not exist on enum $fullClassName");
    }

    /**
     * {@inheritDoc}
     *
     * @return list<ReflectionEnumUnitCase|ReflectionEnumBackedCase>
     */
    public function getCases(): array
    {
        $enumNode = $this->getEnumNode();
        $fullClassName = $this->getName();
        $cases = [];

        foreach ($enumNode->stmts as $stmt) {
            if ($stmt instanceof EnumCase) {
                $cases[] = $this->createEnumCaseReflection($fullClassName, $stmt);
            }
        }

        return $cases;
    }

    /**
     * {@inheritDoc}
     */
    public function hasCase(string $name): bool
    {
        $enumNode = $this->getEnumNode();

        foreach ($enumNode->stmts as $stmt) {
            if ($stmt instanceof EnumCase && $stmt->name->toString() === $name) {
                return true;
            }
        }

        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function isBacked(): bool
    {
        return $this->getEnumNode()->scalarType !== null;
    }

    /**
     * Returns an AST-node for class
     */
    public function getNode(): ClassLike
    {
        return $this->classLikeNode;
    }

    /**
     * Returns the AST node that contains attribute groups for this class.
     */
    protected function getNodeForAttributes(): ClassLike
    {
        return $this->classLikeNode;
    }

    /**
     * Implementation of internal reflection initialization
     */
    protected function __initialize(): void
    {
        /** @var class-string<\UnitEnum> $name */
        $name = $this->getName();
        parent::__construct($name);
    }

    /**
     * Create a ReflectionClass for a given class name.
     *
     * @return \ReflectionClass<object>
     */
    protected function createReflectionForClass(string $className): \ReflectionClass
    {
        if (class_exists($className, false)) {
            /** @var \ReflectionClass<object> */
            return new \ReflectionClass($className);
        }

        return new ReflectionClass($className);
    }

    /**
     * Emulating original behaviour of reflection
     */
    public function __debugInfo(): array
    {
        return [
            'name' => $this->getName()
        ];
    }

    /**
     * Returns the Enum_ AST node, asserting the type
     */
    private function getEnumNode(): Enum_
    {
        assert($this->classLikeNode instanceof Enum_);

        return $this->classLikeNode;
    }

    private function createEnumCaseReflection(string $fullClassName, EnumCase $enumCaseNode): ReflectionEnumUnitCase|ReflectionEnumBackedCase
    {
        if ($this->isBacked() && $enumCaseNode->expr !== null) {
            return new ReflectionEnumBackedCase($fullClassName, $enumCaseNode->name->toString(), $enumCaseNode, $this);
        }

        return new ReflectionEnumUnitCase($fullClassName, $enumCaseNode->name->toString(), $enumCaseNode, $this);
    }
}
