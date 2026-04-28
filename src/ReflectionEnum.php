<?php

declare(strict_types=1);
/**
 * Parser Reflection API
 *
 * @copyright Copyright 2026, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\ParserReflection;

use Go\ParserReflection\Traits\AttributeResolverTrait;
use Go\ParserReflection\Traits\InternalPropertiesEmulationTrait;
use Go\ParserReflection\Traits\ReflectionClassLikeTrait;
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
     * PHP 8.4 virtual typed property providing type-safe access to the classLikeNode as Enum_.
     * No backing store — delegates directly to classLikeNode with a type assertion.
     */
    private Enum_ $enumNode {
        get {
            assert($this->classLikeNode instanceof Enum_);

            return $this->classLikeNode;
        }
    }

    /**
     * Lazily-initialised map of case name → ReflectionEnumUnitCase|ReflectionEnumBackedCase.
     * Keyed by case name for O(1) access in getCase() / hasCase().
     *
     * @var array<string, ReflectionEnumUnitCase|ReflectionEnumBackedCase>
     */
    private array $cases {
        get {
            if (!isset($this->cases)) {
                $fullClassName = $this->getName();
                $isBacked      = $this->isBacked();
                $casesMap      = [];

                foreach ($this->enumNode->stmts as $stmt) {
                    if ($stmt instanceof EnumCase) {
                        $caseName            = $stmt->name->toString();
                        $casesMap[$caseName] = $isBacked && $stmt->expr !== null
                            ? new ReflectionEnumBackedCase($fullClassName, $caseName, $stmt, $this)
                            : new ReflectionEnumUnitCase($fullClassName, $caseName, $stmt, $this);
                    }
                }

                $this->cases = $casesMap;
            }

            return $this->cases;
        }
    }

    /**
     * Initializes reflection instance for an enum
     *
     * @param object|string $argument     Enum class name or instance
     * @param ?Enum_        $classLikeNode AST node for enum
     */
    public function __construct(object|string $argument, ?Enum_ $classLikeNode = null)
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

        $this->classLikeNode = $classLikeNode ?: ReflectionEngine::parseEnum($fullClassName);
    }

    /**
     * {@inheritDoc}
     */
    public function getBackingType(): ?ReflectionNamedType
    {
        if ($this->enumNode->scalarType === null) {
            return null;
        }

        $typeName = $this->enumNode->scalarType->toString();

        return new ReflectionNamedType($typeName, false, true);
    }

    /**
     * {@inheritDoc}
     */
    public function getCase(string $name): ReflectionEnumUnitCase|ReflectionEnumBackedCase
    {
        if (!isset($this->cases[$name])) {
            throw new ReflectionException("Case $name does not exist on enum " . $this->getName());
        }

        return $this->cases[$name];
    }

    /**
     * {@inheritDoc}
     *
     * @return list<ReflectionEnumUnitCase|ReflectionEnumBackedCase>
     */
    public function getCases(): array
    {
        return array_values($this->cases);
    }

    /**
     * {@inheritDoc}
     */
    public function hasCase(string $name): bool
    {
        return isset($this->cases[$name]);
    }

    /**
     * {@inheritDoc}
     */
    public function isBacked(): bool
    {
        return $this->enumNode->scalarType !== null;
    }

    /**
     * Returns an AST-node for class
     */
    public function getNode(): Enum_
    {
        return $this->enumNode;
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
     * Emulating original behaviour of reflection
     */
    public function __debugInfo(): array
    {
        return [
            'name' => $this->getName()
        ];
    }
}
