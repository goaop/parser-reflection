<?php
/**
 * Parser Reflection API
 *
 * @copyright Copyright 2015, Lisachenko Alexander <lisachenko.it@gmail.com>
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
class ReflectionClass extends BaseReflectionClass implements ReflectionInterface
{
    use ReflectionClassLikeTrait, InternalPropertiesEmulationTrait;

    /**
     * Initializes reflection instance
     *
     * @param string|object $argument Class name or instance of object
     * @param ClassLike $classLikeNode AST node for class
     */
    public function __construct($argument, ClassLike $classLikeNode = null)
    {
        $fullClassName       = is_object($argument) ? get_class($argument) : $argument;
        $namespaceParts      = explode('\\', $fullClassName);
        $this->className     = array_pop($namespaceParts);
        // Let's unset original read-only property to have a control over it via __get
        unset($this->name);

        $this->namespaceName = join('\\', $namespaceParts);

        $this->classLikeNode = $classLikeNode;
        if ($this->isParsedNodeMissing()) {
            $this->classLikeNode = ReflectionEngine::parseClass($fullClassName);
        }
        if ($this->classLikeNode && ($this->className !== $this->classLikeNode->name)) {
            throw new \InvalidArgumentException("PhpParser\\Node\\Stmt\\ClassLike's name does not match provided class name.");
        }
    }

    /**
     * Do we need to find the missing parser node?
     */
    private function isParsedNodeMissing()
    {
        if ($this->classLikeNode) {
            return false;
        }
        $isUserDefined = true;
        if ($this->wasIncluded()) {
            $nativeRef = new BaseReflectionClass($this->getName());
            $isUserDefined = $nativeRef->isUserDefined();
        }
        return $isUserDefined;
    }

    /**
     * Parses interfaces from the concrete class node
     *
     * @param ClassLike $classLikeNode Class-like node
     *
     * @return array|\ReflectionClass[] List of reflections of interfaces
     */
    public static function collectInterfacesFromClassNode(ClassLike $classLikeNode)
    {
        $interfaces = [];

        $isInterface    = $classLikeNode instanceof Interface_;
        $interfaceField = $isInterface ? 'extends' : 'implements';
        $hasInterfaces  = in_array($interfaceField, $classLikeNode->getSubNodeNames());
        $implementsList = $hasInterfaces ? $classLikeNode->$interfaceField : array();
        if ($implementsList) {
            foreach ($implementsList as $implementNode) {
                if ($implementNode instanceof FullyQualified) {
                    $implementName              = $implementNode->toString();
                    $interfaces[$implementName] = new static($implementName);
                }
            }
        }

        return $interfaces;
    }

    /**
     * Parses traits from the concrete class node
     *
     * @param ClassLike $classLikeNode Class-like node
     * @param array     $traitAdaptations List of method adaptations
     *
     * @return array|\ReflectionClass[] List of reflections of traits
     */
    public static function collectTraitsFromClassNode(ClassLike $classLikeNode, array &$traitAdaptations)
    {
        $traits = [];

        if (!empty($classLikeNode->stmts)) {
            foreach ($classLikeNode->stmts as $classLevelNode) {
                if ($classLevelNode instanceof TraitUse) {
                    foreach ($classLevelNode->traits as $classTraitName) {
                        if ($classTraitName instanceof FullyQualified) {
                            $traitName          = $classTraitName->toString();
                            $traits[$traitName] = new static($traitName);
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
    public function ___debugInfo()
    {
        return array(
            'name' => $this->getName()
        );
    }

    /**
     * Returns an AST-node for class
     *
     * @return ClassLike
     */
    public function getNode()
    {
        return $this->classLikeNode;
    }

    /**
     * Implementation of internal reflection initialization
     *
     * @return void
     */
    protected function __initialize()
    {
        parent::__construct($this->getName());
    }
}
