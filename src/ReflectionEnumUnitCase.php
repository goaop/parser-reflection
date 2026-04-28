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
use PhpParser\Node\Stmt\EnumCase;
use ReflectionEnumUnitCase as InternalReflectionEnumUnitCase;
use UnitEnum;

/**
 * AST-based reflection for enum unit cases
 */
final class ReflectionEnumUnitCase extends InternalReflectionEnumUnitCase
{
    use InternalPropertiesEmulationTrait;
    use AttributeResolverTrait;

    /**
     * Re-declare to remove PHP 8.4 { get; } hooks so these properties can be unset in constructor
     */
    public string $name;
    public string $class;

    private EnumCase $enumCaseNode;

    private string $enumClassName;

    private ReflectionEnum $enumReflection;

    /**
     * Initializes a reflection for an enum unit case
     */
    public function __construct(
        string $className,
        string $caseName,
        ?EnumCase $enumCaseNode = null,
        ?ReflectionEnum $enumReflection = null
    ) {
        $this->enumClassName = ltrim($className, '\\');

        // Let's unset original read-only property to have a control over it via __get
        unset($this->name, $this->class);

        if ($enumCaseNode === null) {
            $classNode = ReflectionEngine::parseClass($className);
            foreach ($classNode->stmts as $stmt) {
                if ($stmt instanceof EnumCase && $stmt->name->toString() === $caseName) {
                    $enumCaseNode = $stmt;
                    break;
                }
            }
            if ($enumCaseNode === null) {
                throw new ReflectionException("Case $caseName was not found in $className");
            }
        }

        $this->enumCaseNode = $enumCaseNode;
        $this->enumReflection = $enumReflection ?? new ReflectionEnum($className);
    }

    /**
     * Emulating original behaviour of reflection
     */
    public function __debugInfo(): array
    {
        return [
            'name' => $this->enumCaseNode->name->toString(),
            'class' => $this->enumClassName,
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function getEnum(): ReflectionEnum
    {
        return $this->enumReflection;
    }

    /**
     * {@inheritDoc}
     */
    public function getValue(): UnitEnum
    {
        $enumClass = $this->enumClassName;
        $caseName = $this->enumCaseNode->name->toString();

        /** @var UnitEnum $value */
        $value = constant("$enumClass::$caseName");

        return $value;
    }

    /**
     * {@inheritDoc}
     */
    public function getName(): string
    {
        return $this->enumCaseNode->name->toString();
    }

    /**
     * {@inheritDoc}
     */
    public function getDeclaringClass(): ReflectionEnum
    {
        return $this->enumReflection;
    }

    /**
     * {@inheritDoc}
     */
    public function getDocComment(): string|false
    {
        $docComment = $this->enumCaseNode->getDocComment();

        return $docComment ? $docComment->getText() : false;
    }

    /**
     * {@inheritDoc}
     */
    public function isEnumCase(): bool
    {
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function isPublic(): bool
    {
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function isPrivate(): bool
    {
        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function isProtected(): bool
    {
        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function isFinal(): bool
    {
        return true;
    }

    /**
     * Returns the AST node for this enum case
     */
    public function getNode(): EnumCase
    {
        return $this->enumCaseNode;
    }

    /**
     * Returns the AST node that contains attribute groups for this enum case.
     */
    protected function getNodeForAttributes(): EnumCase
    {
        return $this->enumCaseNode;
    }
}
