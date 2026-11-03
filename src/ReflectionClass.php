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
final class ReflectionClass extends InternalReflectionClass implements NodeAwareInterface
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
     * An interface that can not be located is left out with the partial {@see MissingAncestorPolicy}.
     *
     * @return array<string, \ReflectionClass<object>> List of reflections of interfaces
     *
     * @throws ClassNotFoundException If an interface can not be located with the strict policy
     */
    public static function collectInterfacesFromClassNode(ClassLike $classLikeNode): array
    {
        $interfaces = [];

        $interfaceNodes = match (true) {
            $classLikeNode instanceof Interface_                              => $classLikeNode->extends,
            $classLikeNode instanceof Class_, $classLikeNode instanceof Enum_ => $classLikeNode->implements,
            default                                                           => [],
        };
        foreach (self::getResolvedNames($interfaceNodes) as $interfaceName) {
            $interface = self::reflectAncestor($interfaceName);
            if ($interface !== null) {
                $interfaces[$interfaceName] = $interface;
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
     * A trait that can not be located is left out with the partial {@see MissingAncestorPolicy}.
     *
     * @param array<int|string, \PhpParser\Node\Stmt\TraitUseAdaptation> $traitAdaptations List of method adaptations
     *
     * @return \ReflectionClass<object>[] List of reflections of traits
     *
     * @throws ClassNotFoundException If a trait can not be located with the strict policy
     */
    public static function collectTraitsFromClassNode(ClassLike $classLikeNode, array &$traitAdaptations): array
    {
        $traits = [];

        foreach ($classLikeNode->stmts as $classLevelNode) {
            if ($classLevelNode instanceof TraitUse) {
                foreach (self::getResolvedNames($classLevelNode->traits) as $traitName) {
                    $trait = self::reflectAncestor($traitName);
                    if ($trait !== null) {
                        $traits[$traitName] = $trait;
                    }
                }
                $traitAdaptations = array_merge($traitAdaptations, $classLevelNode->adaptations);
            }
        }

        return $traits;
    }

    /**
     * Collects the names of the ancestors of the class node that can not be located
     *
     * @deprecated since 4.2, use getMissingClassNames() of the reflection of the class
     *
     * @return list<string>
     */
    public static function collectMissingAncestorNames(ClassLike $classLikeNode): array
    {
        $className = $classLikeNode->namespacedName?->toString() ?? (string) $classLikeNode->name;

        return (new self($className, $classLikeNode))->getMissingClassNames();
    }

    /**
     * Reflects an ancestor (parent class, interface or trait) of a parsed class
     *
     * An ancestor already declared in the runtime is reflected natively, otherwise its source is located and parsed.
     *
     * @internal
     *
     * @param bool|null $allowMissing Whether to return null instead of throwing when the ancestor can not be
     *                                located, null to follow the {@see MissingAncestorPolicy} of the engine
     *
     * @return \ReflectionClass<object>|null Null if the ancestor can not be located and that is allowed
     *
     * @throws ClassNotFoundException If the ancestor can not be located and that is not allowed
     */
    public static function reflectAncestor(string $ancestorName, ?bool $allowMissing = null): ?InternalReflectionClass
    {
        if (class_exists($ancestorName, false)
            || interface_exists($ancestorName, false)
            || trait_exists($ancestorName, false)
        ) {
            return new parent($ancestorName);
        }

        try {
            return new self($ancestorName);
        } catch (ClassNotFoundException $e) {
            $allowMissing ??= ReflectionEngine::getMissingAncestorPolicy() === MissingAncestorPolicy::Partial;
            if (!$allowMissing) {
                throw $e;
            }

            return null;
        }
    }

    /**
     * Returns the fully qualified names resolved for the given name nodes
     *
     * @param Name[] $nameNodes
     *
     * @return list<string>
     */
    private static function getResolvedNames(array $nameNodes): array
    {
        $resolvedNames = [];
        foreach ($nameNodes as $nameNode) {
            $resolvedName = $nameNode->getAttribute('resolvedName');
            if ($resolvedName instanceof FullyQualified) {
                $resolvedNames[] = $resolvedName->toString();
            }
        }

        return $resolvedNames;
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
}
