<?php
/**
 * Go! AOP framework
 *
 * @copyright Copyright 2015, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace ParserReflection\Traits;

use ParserReflection\ReflectionEngine;
use ParserReflection\ReflectionClass;
use ParserReflection\ReflectionFile;
use ParserReflection\ReflectionFileNamespace;
use ParserReflection\ReflectionMethod;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassConst;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\Trait_;

/**
 * General class-like reflection
 */
trait ReflectionClassLikeTrait
{
    use InitializationTrait;

    /**
     * @var ClassLike
     */
    protected $classLikeNode;

    /**
     * Short name of the class, without namespace
     *
     * @var string
     */
    protected $className;

    /**
     * Namespace name
     *
     * @var string
     */
    protected $namespaceName = '';

    /**
     * @var array|ReflectionMethod
     */
    protected $methods;

    /**
     * Parent class, or false if not present, null if uninitialized yet
     *
     * @var \ReflectionClass|false|null
     */
    protected $parentClass;

    /**
     * Interfaces, empty array or null if not initialized yet
     *
     * @var \ReflectionClass[]|array|null
     */
    protected $interfaceClasses;

    /**
     * List of all constants from the class
     *
     * @var array
     */
    protected $constants;

    /**
     * Emulating original behaviour of reflection
     */
    public function __debugInfo()
    {
        return array(
            'name' => $this->getName()
        );
    }

    /**
     * {@inheritDoc}
     */
    public function getName()
    {
        $namespaceName = $this->namespaceName ? $this->namespaceName . '\\' : '';

        return $namespaceName . $this->getShortName();
    }

    /**
     * {@inheritDoc}
     */
    public function getDocComment()
    {
        return $this->classLikeNode->getDocComment();
    }

    public function getStartLine()
    {
        return $this->classLikeNode->getAttribute('startLine');
    }

    public function getEndLine()
    {
        return $this->classLikeNode->getAttribute('endLine');
    }

    public function getExtension()
    {
        return null;
    }

    public function getExtensionName()
    {
        return '';
    }

    /**
     * {@inheritDoc}
     */
    public function getFileName()
    {
        return ReflectionEngine::locateClassFile($this->getName());
    }

    /**
     * {@inheritDoc}
     */
    public function getNamespaceName()
    {
        return $this->namespaceName;
    }

    /**
     * {@inheritDoc}
     */
    public function inNamespace()
    {
        return !empty($this->namespaceName);
    }

    /**
     * Returns the reflection of current file namespace
     *
     * @return ReflectionFileNamespace
     */
    public function getFileNamespace()
    {
        return new ReflectionFileNamespace($this->getFileName(), $this->namespaceName);
    }

    /**
     * Returns the reflection of current file
     *
     * @return ReflectionFile
     */
    public function getFile()
    {
        return new ReflectionFile($this->getFileName());
    }

    /**
     * {@inheritDoc}
     */
    public function getConstants()
    {
        if (!isset($this->constants)) {
            $this->constants = $this->findConstants();
        }

        // TODO: collect constants from all parents

        return $this->constants;
    }

    /**
     * {@inheritDoc}
     */
    public function hasConstant($name)
    {
        $constants   = $this->getConstants();
        $hasConstant = isset($constants[$name]) || array_key_exists($constants, $name);

        return $hasConstant;
    }

    /**
     * {@inheritDoc}
     */
    public function getConstant($name)
    {
        if ($this->hasConstant($name)) {
            return $this->constants[$name];
        }

        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function getInterfaces()
    {
        if (!isset($this->interfaceClasses)) {
            $this->interfaceClasses = $this->recursiveCollect(function (array &$result, \ReflectionClass $instance) {
                if ($instance->isInterface()) {
                    $result[$instance->getName()] = $instance;
                }
                $result += $instance->getInterfaces();
            });
        }

        return $this->interfaceClasses;
    }

    /**
     * {@inheritDoc}
     */
    public function getInterfaceNames()
    {
        return array_keys($this->getInterfaces());
    }

    /**
     * {@inheritDoc}
     */
    public function isUserDefined()
    {
        // always defined by user, because we parse the source code
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function isInternal()
    {
        // never can be an internal method
        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function getShortName()
    {
        return $this->className;
    }

    /**
     * {@inheritDoc}
     */
    public function isInstantiable()
    {
        if ($this->isInterface() || $this->isAbstract()) {
            return false;
        }

        if (null === ($constructor = $this->getConstructor())) {
            return true;
        }

        return $constructor->isPublic();
    }

    /**
     * {@inheritDoc}
     */
    public function getConstructor()
    {
        $constructor = $this->getMethod('__construct');
        if (!$constructor) {
            return null;
        }

        return $constructor;
    }

    /**
     * {@inheritDoc}
     */
    public function isCloneable()
    {
        if ($this->isInterface() || $this->isAbstract()) {
            return false;
        }

        if ($this->hasMethod('__clone')) {
            return $this->getMethod('__clone')->isPublic();
        }

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function isAbstract()
    {
        if ($this->classLikeNode instanceof Class_ && $this->classLikeNode->isAbstract()) {
            return true;
        } elseif ($this->isInterface() && !empty($this->methods)) {
            return true;
        }

        return false;
    }

    /**
     * Currently, anonymous classes aren't supported for parsed reflection
     */
    public function isAnonymous()
    {
        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function isFinal()
    {
        $isFinal = $this->classLikeNode instanceof Class_ && $this->classLikeNode->isFinal();

        return $isFinal;
    }

    /**
     * {@inheritDoc}
     */
    public function isInstance($object)
    {
        if (!is_object($object)) {
            throw new \RuntimeException(sprintf('Parameter must be an object, "%s" provided.', gettype($object)));
        }

        $className = $this->getName();

        return $className === get_class($object) || is_subclass_of($object, $className);
    }

    /**
     * {@inheritDoc}
     */
    public function isSubclassOf($class)
    {
        if (is_object($class)) {
            if ($class instanceof ReflectionClass) {
                $class = $class->getName();
            } else {
                $class = get_class($class);
            }
        }

        if (!$this->classLikeNode instanceof Class_) {
            return false;
        } else{
            $extends = $this->classLikeNode->extends;
            if ($extends && $extends->toString() == $class) {
                return true;
            }
        }

        $parent = $this->getParentClass();

        return false === $parent ? false : $parent->isSubclassOf($class);
    }

    /**
     * {@inheritDoc}
     */
    public function getParentClass()
    {
        if (!isset($this->parentClass)) {
            static $extendsField = 'extends';

            $parentClass = false;
            $hasExtends  = in_array($extendsField, $this->classLikeNode->getSubNodeNames());
            $extendsNode = $hasExtends ? $this->classLikeNode->$extendsField : null;
            if ($extendsNode instanceof FullyQualified) {
                $extendsName = $extendsNode->toString();
                $parentClass = class_exists($extendsName, false) ? new parent($extendsName) : new static($extendsName);
            }
            $this->parentClass = $parentClass;
        }

        return $this->parentClass;
    }

    /**
     * Returns list of reflection methods
     *
     * @return ReflectionMethod[]|array
     */
    public function getMethods($filter = null)
    {
        if (!isset($this->methods)) {
            $directMethods = $this->getDirectMethods();
            $parentMethods = $this->recursiveCollect(function (array &$result, \ReflectionClass $instance) {
                $result = array_merge($result, $instance->getMethods());
            });
            $methods = array_merge($directMethods, $parentMethods);

            $this->methods = $methods;
        }
        // TODO: Implement filtration of methods

        return $this->methods;
    }

    /**
     * {@inheritdoc}
     */
    public function getMethod($name)
    {
        $methods = $this->getMethods();
        foreach ($methods as $method) {
            if ($method->getName() == $name) {
                return $method;
            }
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function hasMethod($name)
    {
        $methods = $this->getMethods();
        foreach ($methods as $method) {
            if ($method->getName() == $name) {
                return true;
            }
        }

        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function isIterateable()
    {
        return $this->implementsInterface('Traversable');
    }

    /**
     * {@inheritDoc}
     */
    public function implementsInterface($interfaceName)
    {
        $allInterfaces = $this->getInterfaces();

        return isset($allInterfaces[$interfaceName]);
    }

    /**
     * {@inheritDoc}
     */
    public function isInterface()
    {
        return ($this->classLikeNode instanceof Interface_);
    }

    /**
     * {@inheritDoc}
     */
    public function isTrait()
    {
        return ($this->classLikeNode instanceof Trait_);
    }

    private function getDirectInterfaces()
    {
        $interfaces = array();

        $interfaceField = $this->isInterface() ? 'extends' : 'implements';
        $hasInterfaces  = in_array($interfaceField, $this->classLikeNode->getSubNodeNames());
        $implementsList = $hasInterfaces ? $this->classLikeNode->$interfaceField : array();
        if ($implementsList) {
            foreach ($implementsList as $implementNode) {
                if ($implementNode instanceof FullyQualified) {
                    $implementName  = $implementNode->toString();
                    $interface      = interface_exists($implementName, false)
                        ? new parent($implementName)
                        : new static($implementName);
                    $interfaces[$implementName] = $interface;
                }
            }
        }

        return $interfaces;
    }

    private function getDirectMethods()
    {
        $methods = array();

        foreach ($this->classLikeNode->stmts as $classLevelNode) {
            if ($classLevelNode instanceof ClassMethod) {
                $methods[] = new ReflectionMethod(
                    $this->getName(),
                    $classLevelNode->name,
                    $classLevelNode
                );
            }
        }

        return $methods;
    }

    private function recursiveCollect(\Closure $collector)
    {
        $result = array();

        $parentClass = $this->getParentClass();
        if ($parentClass) {
            $collector($result, $parentClass);
        }

        $interfaces = $this->getDirectInterfaces();
        foreach ($interfaces as $interface) {
            $collector($result, $interface);
        }

        return $result;
    }

    /**
     * Returns list of constants from the class
     *
     * @return array
     */
    private function findConstants()
    {
        $constants = array();

        // constants can be only top-level nodes in the class, so we can scan them directly
        foreach ($this->classLikeNode->stmts as $classLevelNode) {
            if ($classLevelNode instanceof ClassConst) {
                $nodeConstants = $classLevelNode->consts;
                if ($nodeConstants) {
                    // TODO: normalize values of constants to the PHP expressions
                    $constants[$nodeConstants[0]->name] = $nodeConstants[0]->value;
                }
            }
        }

        return $constants;
    }
}