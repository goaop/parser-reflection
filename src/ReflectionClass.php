<?php
/**
 * Created by PhpStorm.
 * User: 1
 * Date: 22.03.2015
 * Time: 12:12
 */

namespace ParserReflection;

use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassConst;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\Trait_;
use ReflectionClass as InternalReflectionClass;

class ReflectionClass extends InternalReflectionClass
{

    /**
     * Class node
     *
     * @var ClassLike
     */
    private $classLikeNode;

    /**
     * Namespace node reference
     *
     * @var ReflectionFileNamespace
     */
    private $fileNamespace;

    /**
     * Is internal reflection is initialized or not
     *
     * @var boolean
     */
    private $isInitialized = false;

    /**
     * @var array
     */
    protected $constants;

    /**
     * @var array|ReflectionMethod[]
     */
    protected $methods;

    public function __construct(ClassLike $classLikeNode, ReflectionFileNamespace $fileNamespace)
    {
        $this->classLikeNode = $classLikeNode;
        $this->fileNamespace = $fileNamespace;
    }

    /**
     * {@inheritDoc}
     */
    public function getName()
    {
        return $this->fileNamespace->getName() . '\\' . $this->getShortName();
    }

    /**
     * {@inheritDoc}
     */
    public function getShortName()
    {
        return $this->classLikeNode->name;
    }

    /**
     * {@inheritDoc}
     */
    public function getStartLine()
    {
        return $this->classLikeNode->getAttribute('startLine');
    }

    /**
     * {@inheritDoc}
     */
    public function getEndLine()
    {
        return $this->classLikeNode->getAttribute('endLine');
    }

    /**
     * Gets doc comments from a class.
     *
     * @return string|false The doc comment if it exists, otherwise "false"
     */
    public function getDocComment()
    {
        $docComment = false;
        $comments   = $this->classLikeNode->getAttribute('comments');

        if ($comments) {
            $docComment = (string) $comments[0];
        }

        return $docComment;
    }

    /**
     * {@inheritDoc}
     */
    public function getExtension()
    {
        // For user-defined classes this will be always null
        return null;
    }

    /**
     * {@inheritDoc}
     */
    public function getExtensionName()
    {
        // For user-defined classes this will be always false
        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function getFileName()
    {
        return $this->fileNamespace->getName();
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
     * {@inheritdoc}
     */
    public function getMethods($filter = null, $returnKeys = false)
    {
        if (!isset($this->methods)) {
            $this->methods = $this->findMethods();
        }

        return $returnKeys ? $this->methods : array_values($this->methods);
    }

    /**
     * {@inheritdoc}
     */
    public function getMethod($name)
    {
        if ($this->hasMethod($name)) {
            return $this->methods[$name];
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function hasMethod($name)
    {
        $methods   = $this->getMethods(-1, $returnKeys = true);
        $hasMethod = isset($methods[$name]);

        return $hasMethod;
    }


    /**
     * {@inheritDoc}
     */
    public function isInternal()
    {
        // user-defined class can not be internal
        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function isUserDefined()
    {
        // this is always user-defined class
        return true;
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
            if ($class instanceof InternalReflectionClass) {
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
    public function isIterateable()
    {
        return $this->implementsInterface('Traversable');
    }

    /**
     * Initializes internal reflection for calling misc runtime methods
     */
    public function initializeInternalReflection()
    {
        if (!$this->isInitialized) {
            parent::__construct($this->getName());
            $this->isInitialized = true;
        }
    }

    /**
     * Returns the status of initialization status for internal object
     *
     * @return bool
     */
    public function isInitialized()
    {
        return $this->isInitialized;
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

    private function findMethods()
    {
        $methods = array();

        foreach ($this->classLikeNode->stmts as $classLevelNode) {
            if ($classLevelNode instanceof ClassMethod) {
                $methods[$classLevelNode->name] = new ReflectionMethod($classLevelNode, $this);
            }
        }

        return $methods;
    }
}