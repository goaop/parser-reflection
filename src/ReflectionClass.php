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
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Enum_;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\TraitUse;
use ReflectionClass as InternalReflectionClass;

/**
 * AST-based reflection class
 *
 * @see \Go\ParserReflection\ReflectionClassTest
 * @extends \ReflectionClass<object>
 */
final class ReflectionClass extends InternalReflectionClass
{
    use InternalPropertiesEmulationTrait;
    use ReflectionClassLikeTrait;
    use AttributeResolverTrait;

    /**
     * Re-declare to remove parent's @readonly / PHP 8.4 hook so it can be unset in constructor
     */
    public string $name;

    /**
     * Initializes reflection instance
     *
     * @param object|string $argument      Class name or instance of object
     * @param ?ClassLike    $classLikeNode AST node for class
     */
    public function __construct(object|string $argument, ?ClassLike $classLikeNode = null)
    {
        $fullClassName = is_object($argument) ? get_class($argument) : ltrim($argument, '\\');
        $namespaceParts  = explode('\\', $fullClassName);
        $shortName = array_pop($namespaceParts);
        if ($shortName !== '') {
            $this->className = $shortName;
        } else {
            // Fallback: use the full class name if explode produced an empty short name
            // get_class() always returns non-empty, so this path handles edge cases only
            $this->className = $fullClassName !== '' ? $fullClassName : 'UnknownClass';
        }
        // Let's unset original read-only property to have a control over it via __get
        unset($this->name);

        $this->namespaceName = implode('\\', $namespaceParts);

        $this->classLikeNode = $classLikeNode ?: ReflectionEngine::parseClass($fullClassName);
    }

    /**
     * Parses interfaces from the concrete class node
     *
     * @return array<string, \ReflectionClass<object>> List of reflections of interfaces
     */
    public static function collectInterfacesFromClassNode(ClassLike $classLikeNode): array
    {
        $interfaces = [];

        if ($classLikeNode instanceof Interface_) {
            $implementsList = $classLikeNode->extends;
        } elseif ($classLikeNode instanceof Class_ || $classLikeNode instanceof Enum_) {
            $implementsList = $classLikeNode->implements;
        } else {
            $implementsList = [];
        }

        if (count($implementsList) > 0) {
            foreach ($implementsList as $implementNode) {
                if ($implementNode->getAttribute('resolvedName') instanceof FullyQualified) {
                    $implementName = $implementNode->getAttribute('resolvedName')->toString();
                    $interface     = interface_exists($implementName, false)
                        ? new parent($implementName)
                        : new self($implementName);

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
                $interfaces[$interfaceToAdd] = self::createNativeReflectionClass($interfaceToAdd);
            }
        }

        return $interfaces;
    }

    /**
     * Parses traits from the concrete class node
     *
     * @param array<int|string, \PhpParser\Node\Stmt\TraitUseAdaptation> $traitAdaptations List of method adaptations
     *
     * @return \ReflectionClass<object>[] List of reflections of traits
     */
    public static function collectTraitsFromClassNode(ClassLike $classLikeNode, array &$traitAdaptations): array
    {
        $traits = [];

        if (!empty($classLikeNode->stmts)) {
            foreach ($classLikeNode->stmts as $classLevelNode) {
                if ($classLevelNode instanceof TraitUse) {
                    foreach ($classLevelNode->traits as $classTraitName) {
                        if ($classTraitName->getAttribute('resolvedName') instanceof FullyQualified) {
                            $traitName          = $classTraitName->getAttribute('resolvedName')->toString();
                            $trait              = trait_exists($traitName, false)
                                ? new parent($traitName)
                                : new self($traitName);
                            $traits[$traitName] = $trait;
                        }
                    }
                    $traitAdaptations = array_merge($traitAdaptations, $classLevelNode->adaptations);
                }
            }
        }

        return $traits;
    }

    /**
     * Creates a native ReflectionClass instance for the given class/interface name.
     *
     * @param class-string<object> $className
     * @return \ReflectionClass<object>
     */
    private static function createNativeReflectionClass(string $className): InternalReflectionClass
    {
        return new parent($className);
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
        parent::__construct($this->getName());
    }

    /**
     * Create a ReflectionClass for a given class name.
     *
     * @param string $className The name of the class to create a reflection for.
     *
     * @return \ReflectionClass<object> The appropriate reflection object.
     */
    protected function createReflectionForClass(string $className): InternalReflectionClass
    {
        return class_exists($className, false) ? new parent($className) : new self($className);
    }
}
