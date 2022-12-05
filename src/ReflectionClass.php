<?php
/**
 * Parser Reflection API
 *
 * @copyright Copyright 2015-2022, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */
declare(strict_types=1);

namespace Go\ParserReflection;

use Go\ParserReflection\Traits\InternalPropertiesEmulationTrait;
use Go\ParserReflection\Traits\ReflectionClassLikeTrait;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\TraitUse;
use ReflectionClass as BaseReflectionClass;
use TypeError;

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
     * @throws ReflectionException
     *
     * @noinspection PhpMissingParentConstructorInspection
     */
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
     * @return ReflectionClass[] List of reflections of interfaces
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

                    try {
                        $interface = new static($implementName);

                        $interfaces[$implementName] = $interface;
                    } catch (ReflectionException) {
                        // Ignore interfaces that cannot be parsed
                    }
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
     * @return ReflectionClass[] List of reflections of traits
     */
    public static function collectTraitsFromClassNode(ClassLike $classLikeNode, array &$traitAdaptations): array
    {
        $traits = [];

        if (!empty($classLikeNode->stmts)) {
            foreach ($classLikeNode->stmts as $classLevelNode) {
                if ($classLevelNode instanceof TraitUse) {
                    foreach ($classLevelNode->traits as $classTraitName) {
                        if ($classTraitName instanceof FullyQualified) {
                            $traitName = $classTraitName->toString();

                            try {
                                $trait = new static($traitName);
                                $traits[$traitName] = $trait;
                            } catch (ReflectionException) {
                                // Ignore traits that cannot be parsed
                            }
                        }
                    }
                    $traitAdaptations = $classLevelNode->adaptations;
                }
            }
        }

        return $traits;
    }

    /**
     * Emulating original behaviour of reflection.
     *
     * Called when invoking {@link var_dump()} on an object
     *
     * @return array{name: string}
     */
    public function __debugInfo(): array
    {
        try {
            $name = $this->getName();
        } catch (TypeError) {
            // If we are here, then we are in the middle of the object creation
            $name = null;
        }

        return [
            'name' => $name,
        ];
    }

    /**
     * Returns an AST-node for class
     *
     * @return ClassLike
     */
    public function getNode(): ClassLike
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
     * @return ReflectionClass The appropriate reflection object.
     *
     * @throws ReflectionException
     */
    protected function createReflectionForClass(string $className): ReflectionClass
    {
        return new static($className);
    }
}
