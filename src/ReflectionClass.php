<?php

declare(strict_types=1);
/**
 * Parser Reflection API
 *
 * @copyright Copyright 2015-2022, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\ParserReflection;

use Go\ParserReflection\Traits\InternalPropertiesEmulationTrait;
use Go\ParserReflection\Traits\ReflectionClassLikeTrait;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\TraitUse;
use ReflectionClass as BaseReflectionClass;

/**
 * AST-based reflection class
 */
class ReflectionClass extends BaseReflectionClass
{
    use InternalPropertiesEmulationTrait;
    use ReflectionClassLikeTrait;

    /**
     * Initializes reflection instance
     *
     * @param object|string $argument      Class name or instance of object
     * @param ?ClassLike    $classLikeNode AST node for class
     *
     * @noinspection PhpMissingParentConstructorInspection*/
    public function __construct(object|string $argument, ClassLike $classLikeNode = null)
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
     * Magic getter
     *
     * @param string $propertyName
     *
     * @return string|null
     */
    public function __get(string $propertyName)
    {
        if ($propertyName === 'name') {
            return $this->getName();
        }
        return $this->$propertyName();
    }

    /**
     * Parses interfaces from the concrete class node
     *
     * @return BaseReflectionClass[] List of reflections of interfaces
     *
     * @noinspection PhpDocMissingThrowsInspection
     */
    public static function collectInterfacesFromClassNode(ClassLike $classLikeNode): array
    {
        $interfaces = [];

        $isInterface    = $classLikeNode instanceof Interface_;
        $interfaceField = $isInterface ? 'extends' : 'implements';
        $hasInterfaces  = in_array($interfaceField, $classLikeNode->getSubNodeNames(), true);
        $implementsList = $hasInterfaces ? $classLikeNode->$interfaceField : [];
        if ($implementsList) {
            foreach ($implementsList as $implementNode) {
                if ($implementNode instanceof FullyQualified) {
                    $implementName = $implementNode->toString();
                    /** @noinspection PhpUnhandledExceptionInspection */
                    $interface     = interface_exists($implementName, false)
                        ? new parent($implementName)
                        : new static($implementName);

                    $interfaces[$implementName] = $interface;
                }
            }
        }

        return $interfaces;
    }

    /**
     * Parses traits from the concrete class node
     *
     * @param array $traitAdaptations List of method adaptations
     *
     * @return BaseReflectionClass[] List of reflections of traits
     *
     * @noinspection PhpDocMissingThrowsInspection
     */
    public static function collectTraitsFromClassNode(ClassLike $classLikeNode, array &$traitAdaptations): array
    {
        $traits = [];

        if (!empty($classLikeNode->stmts)) {
            foreach ($classLikeNode->stmts as $classLevelNode) {
                if ($classLevelNode instanceof TraitUse) {
                    foreach ($classLevelNode->traits as $classTraitName) {
                        if ($classTraitName instanceof FullyQualified) {
                            $traitName          = $classTraitName->toString();
                            /** @noinspection PhpUnhandledExceptionInspection */
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
        return [];
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
     *
     * @return void
     *
     * @noinspection PhpDocMissingThrowsInspection
     */
    protected function __initialize(): void
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        parent::__construct($this->getName());
    }

    /**
     * Create a ReflectionClass for a given class name.
     *
     * @param string $className The name of the class to create a reflection for.
     *
     * @return BaseReflectionClass The appropriate reflection object.
     *
     * @noinspection PhpDocMissingThrowsInspection
     */
    protected function createReflectionForClass(string $className): BaseReflectionClass
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        return class_exists($className, false) ? new parent($className) : new static($className);
    }
}
