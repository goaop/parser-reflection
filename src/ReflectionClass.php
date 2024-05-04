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

namespace Go\ParserReflection;

use Go\ParserReflection\Traits\AttributeResolverTrait;
use Go\ParserReflection\Traits\InternalPropertiesEmulationTrait;
use Go\ParserReflection\Traits\ReflectionClassLikeTrait;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Enum_;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\TraitUse;
use ReflectionClass as InternalReflectionClass;

/**
 * AST-based reflection class
 * @see \Go\ParserReflection\ReflectionClassTest
 */
class ReflectionClass extends InternalReflectionClass
{
    use InternalPropertiesEmulationTrait;
    use ReflectionClassLikeTrait;
    use AttributeResolverTrait;

    /**
     * Initializes reflection instance
     *
     * @param object|string $argument      Class name or instance of object
     * @param ?ClassLike    $classLikeNode AST node for class
     */
    public function __construct(object|string $argument, ?ClassLike $classLikeNode = null)
    {
        $fullClassName   = is_object($argument) ? get_class($argument) : ltrim($argument, '\\');
        $namespaceParts  = explode('\\', $fullClassName);
        $this->className = array_pop($namespaceParts);
        // Let's unset original read-only property to have a control over it via __get
        unset($this->name);

        $this->namespaceName = implode('\\', $namespaceParts);

        $this->classLikeNode = $classLikeNode ?: ReflectionEngine::parseClass($fullClassName);
    }

    /**
     * Parses interfaces from the concrete class node
     *
     * @return InternalReflectionClass[] List of reflections of interfaces
     */
    public static function collectInterfacesFromClassNode(ClassLike $classLikeNode): array
    {
        $interfaces = [];

        $isInterface    = $classLikeNode instanceof Interface_;
        $interfaceField = $isInterface ? 'extends' : 'implements';

        $hasExplicitInterfaces = in_array($interfaceField, $classLikeNode->getSubNodeNames(), true);
        $implementsList        = $hasExplicitInterfaces ? $classLikeNode->$interfaceField : [];

        if (count($implementsList) > 0) {
            foreach ($implementsList as $implementNode) {
                if ($implementNode instanceof Name && $implementNode->getAttribute('resolvedName') instanceof FullyQualified) {
                    $implementName = $implementNode->getAttribute('resolvedName')->toString();
                    $interface     = interface_exists($implementName, false)
                        ? new parent($implementName)
                        : new static($implementName);

                    $interfaces[$implementName] = $interface;
                }
            }
        }

        // All Enum classes has implicit interface(s) added by PHP
        if ($classLikeNode instanceof Enum_) {
            // @see https://php.watch/versions/8.1/enums#enum-BackedEnum
            $interfacesToAdd = isset($classLikeNode->scalarType)
                ? [\UnitEnum::class, \BackedEnum::class] // PHP Uses exactly this order, not reversed by parent!
                : [\UnitEnum::class];
            foreach ($interfacesToAdd as $interfaceToAdd) {
                $interfaces[$interfaceToAdd] = new parent($interfaceToAdd);
            }
        }

        return $interfaces;
    }

    /**
     * Parses traits from the concrete class node
     *
     * @param array $traitAdaptations List of method adaptations
     *
     * @return InternalReflectionClass[] List of reflections of traits
     */
    public static function collectTraitsFromClassNode(ClassLike $classLikeNode, array &$traitAdaptations): array
    {
        $traits = [];

        if (!empty($classLikeNode->stmts)) {
            foreach ($classLikeNode->stmts as $classLevelNode) {
                if ($classLevelNode instanceof TraitUse) {
                    foreach ($classLevelNode->traits as $classTraitName) {
                        if ($classTraitName instanceof Name && $classTraitName->getAttribute('resolvedName') instanceof FullyQualified) {
                            $traitName          = $classTraitName->getAttribute('resolvedName')->toString();
                            $trait              = trait_exists($traitName, false)
                                ? new parent($traitName)
                                : new static($traitName);
                            $traits[$traitName] = $trait;
                        }
                    }
                    $traitAdaptations = $classLevelNode->adaptations;
                }
            }
        }

        return $traits;
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
     * Returns an AST-node for class
     */
    public function getNode(): ?ClassLike
    {
        return $this->classLikeNode;
    }

    /**
     * Implementation of internal reflection initialization
     */
    protected function __initialize(): void
    {
        parent::__construct($this->getName());
    }

    /**
     * Create a ReflectionClass for a given class name.
     *
     * @param string $className The name of the class to create a reflection for.
     *
     * @return InternalReflectionClass The appropriate reflection object.
     */
    protected function createReflectionForClass(string $className): InternalReflectionClass
    {
        return class_exists($className, false) ? new parent($className) : new static($className);
    }
}
