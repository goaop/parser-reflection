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
use Go\ParserReflection\Traits\ReflectionFunctionLikeTrait;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use ReflectionMethod as BaseReflectionMethod;
use ReflectionClass as BaseReflectionClass;

/**
 * AST-based reflection for the method in a class
 */
class ReflectionMethod extends BaseReflectionMethod implements ReflectionInterface
{
    use ReflectionFunctionLikeTrait, InternalPropertiesEmulationTrait;

    /**
     * Name of the class
     *
     * @var string
     */
    private $className;

    /**
     * Optional declaring class reference
     *
     * @var ReflectionClass
     */
    private $declaringClass;

    /**
     * Name of method
     *
     * @var string
     */
    private $methodName;

    /**
     * Initializes reflection instance for the method node
     *
     * @param string $className Name of the class
     * @param string $methodName Name of the method
     * @param ClassMethod $classMethodNode AST-node for method
     * @param ReflectionClass $declaringClass Optional declaring class
     */
    public function __construct(
        $className,
        $methodName,
        ClassMethod $classMethodNode = null,
        ReflectionClass $declaringClass = null
    ) {
        //for some reason, ReflectionMethod->getNamespaceName in php always returns '', so we shouldn't use it too
        $this->className        = $className;
        $this->methodName       = $methodName;
        $this->declaringClass   = $declaringClass;
        $this->functionLikeNode = $classMethodNode;
        if ($this->isParsedNodeMissing()) {
            $this->functionLikeNode = ReflectionEngine::parseClassMethod($className, $methodName);
        }
        // Let's unset original read-only properties to have a control over them via __get
        unset($this->name, $this->class);

        if ($this->functionLikeNode && ($this->methodName !== $this->functionLikeNode->name)) {
            throw new \InvalidArgumentException("PhpParser\\Node\\Stmt\\ClassMethod's name does not match provided method name.");
        }
    }

    /**
     * Are we missing the parser node?
     */
    private function isParsedNodeMissing()
    {
        if ($this->functionLikeNode) {
            return false;
        }
        $isUserDefined = true;
        if ($this->wasIncluded()) {
            $nativeRef = new BaseReflectionClass($this->className);
            $isUserDefined = $nativeRef->isUserDefined();
        }
        return $isUserDefined;
    }

    /**
     * Returns an AST-node for method
     *
     * @return ClassMethod
     */
    public function getNode()
    {
        return $this->functionLikeNode;
    }

    /**
     * Emulating original behaviour of reflection
     */
    public function ___debugInfo()
    {
        return [
            'name'  => $this->methodName,
            'class' => $this->className
        ];
    }

    /**
     * Returns the string representation of the Reflection method object.
     *
     * @link http://php.net/manual/en/reflectionmethod.tostring.php
     *
     * @return string
     */
    public function __toString()
    {
        if (!$this->functionLikeNode) {
            $this->initializeInternalReflection();
            return parent::__toString();
        }
        // Internally $this->getReturnType() !== null is the same as $this->hasReturnType()
        $returnType       = $this->getReturnType();
        $hasReturnType    = $returnType !== null;
        $paramsNeeded     = $hasReturnType || $this->getNumberOfParameters() > 0;
        $paramFormat      = $paramsNeeded ? "\n\n  - Parameters [%d] {%s\n  }" : '';
        $returnFormat     = $hasReturnType ? "\n  - Return [ %s ]" : '';
        $methodParameters = $this->getParameters();
        try {
            $prototype = $this->getPrototype();
        } catch (\ReflectionException $e) {
            $prototype = null;
        }
        $prototypeClass = $prototype ? $prototype->getDeclaringClass()->name : '';

        $paramString = '';
        $identation  = str_repeat(' ', 4);
        foreach ($methodParameters as $methodParameter) {
            $paramString .= "\n{$identation}" . $methodParameter;
        }

        return sprintf(
            "%sMethod [ <user%s%s%s>%s%s%s %s method %s ] {\n  @@ %s %d - %d{$paramFormat}{$returnFormat}\n}\n",
            $this->getDocComment() ? $this->getDocComment() . "\n" : '',
            $prototype ? ", overwrites {$prototypeClass}, prototype {$prototypeClass}" : '',
            $this->isConstructor() ? ', ctor' : '',
            $this->isDestructor() ? ', dtor' : '',
            $this->isFinal() ? ' final' : '',
            $this->isStatic() ? ' static' : '',
            $this->isAbstract() ? ' abstract' : '',
            join(
                ' ',
                \Reflection::getModifierNames(
                    $this->getModifiers() & (self::IS_PUBLIC | self::IS_PROTECTED | self::IS_PRIVATE)
                )
            ),
            $this->getName(),
            $this->getFileName(),
            $this->getStartLine(),
            $this->getEndLine(),
            count($methodParameters),
            $paramString,
            $returnType ? ReflectionType::convertToDisplayType($returnType) : ''
        );
    }

    /**
     * {@inheritDoc}
     */
    public function getClosure($object)
    {
        $this->initializeInternalReflection();

        return parent::getClosure($object);
    }

    /**
     * {@inheritDoc}
     */
    public function getDeclaringClass()
    {
        return isset($this->declaringClass) ? $this->declaringClass : new ReflectionClass($this->className);
    }

    /**
     * {@inheritDoc}
     */
    public function getModifiers()
    {
        $modifiers = 0;
        if ($this->isPublic()) {
            $modifiers += self::IS_PUBLIC;
        }
        if ($this->isProtected()) {
            $modifiers += self::IS_PROTECTED;
        }
        if ($this->isPrivate()) {
            $modifiers += self::IS_PRIVATE;
        }
        if ($this->isAbstract()) {
            $modifiers += self::IS_ABSTRACT;
        }
        if ($this->isFinal()) {
            $modifiers += self::IS_FINAL;
        }
        if ($this->isStatic()) {
            $modifiers += self::IS_STATIC;
        }

        return $modifiers;
    }

    /**
     * {@inheritDoc}
     */
    public function getPrototype()
    {
        $parent = $this->getDeclaringClass()->getParentClass();
        if (!$parent) {
            throw new ReflectionException("No prototype");
        }

        $prototypeMethod = $parent->getMethod($this->getName());
        if (!$prototypeMethod) {
            throw new ReflectionException("No prototype");
        }

        return $prototypeMethod;
    }

    /**
     * {@inheritDoc}
     */
    public function invoke($object, $args = null)
    {
        $this->initializeInternalReflection();

        return call_user_func_array('parent::invoke', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function invokeArgs($object, array $args)
    {
        $this->initializeInternalReflection();

        return parent::invokeArgs($object, $args);
    }

    /**
     * {@inheritDoc}
     */
    public function isAbstract()
    {
        if (!$this->functionLikeNode) {
            $this->initializeInternalReflection();
            return parent::isAbstract();
        }
        return $this->getDeclaringClass()->isInterface() || $this->getClassMethodNode()->isAbstract();
    }

    /**
     * {@inheritDoc}
     */
    public function isConstructor()
    {
        if (!$this->functionLikeNode) {
            $this->initializeInternalReflection();
            return parent::isConstructor();
        }
        return $this->getClassMethodNode()->name == '__construct';
    }

    /**
     * {@inheritDoc}
     */
    public function isDestructor()
    {
        if (!$this->functionLikeNode) {
            $this->initializeInternalReflection();
            return parent::isDestructor();
        }
        return $this->getClassMethodNode()->name == '__destruct';
    }

    /**
     * {@inheritDoc}
     */
    public function isFinal()
    {
        if (!$this->functionLikeNode) {
            $this->initializeInternalReflection();
            return parent::isFinal();
        }
        return $this->getClassMethodNode()->isFinal();
    }

    /**
     * {@inheritDoc}
     */
    public function isPrivate()
    {
        if (!$this->functionLikeNode) {
            $this->initializeInternalReflection();
            return parent::isPrivate();
        }
        return $this->getClassMethodNode()->isPrivate();
    }

    /**
     * {@inheritDoc}
     */
    public function isProtected()
    {
        if (!$this->functionLikeNode) {
            $this->initializeInternalReflection();
            return parent::isProtected();
        }
        return $this->getClassMethodNode()->isProtected();
    }

    /**
     * {@inheritDoc}
     */
    public function isPublic()
    {
        if (!$this->functionLikeNode) {
            $this->initializeInternalReflection();
            return parent::isPublic();
        }
        return $this->getClassMethodNode()->isPublic();
    }

    /**
     * {@inheritDoc}
     */
    public function isStatic()
    {
        if (!$this->functionLikeNode) {
            $this->initializeInternalReflection();
            return parent::isStatic();
        }
        return $this->getClassMethodNode()->isStatic();
    }

    /**
     * {@inheritDoc}
     */
    public function setAccessible($accessible)
    {
        $this->initializeInternalReflection();

        parent::setAccessible($accessible);
    }

    /**
     * Parses methods from the concrete class node
     *
     * @param ClassLike $classLikeNode Class-like node
     * @param ReflectionClass $reflectionClass Reflection of the class
     *
     * @return array|ReflectionMethod[]
     */
    public static function collectFromClassNode(ClassLike $classLikeNode, ReflectionClass $reflectionClass)
    {
        $methods = [];

        foreach ($classLikeNode->stmts as $classLevelNode) {
            if ($classLevelNode instanceof ClassMethod) {
                $classLevelNode->setAttribute('fileName', $classLikeNode->getAttribute('fileName'));

                $methodName = $classLevelNode->name;
                $methods[$methodName] = new ReflectionMethod(
                    $reflectionClass->name,
                    $methodName,
                    $classLevelNode,
                    $reflectionClass
                );
            }
        }

        return $methods;
    }

    /**
     * Implementation of internal reflection initialization
     *
     * @return void
     */
    protected function __initialize()
    {
        parent::__construct($this->className, $this->methodName);
    }

    /**
     * Returns ClassMethod node to prevent all possible type checks with instanceof
     *
     * @return ClassMethod
     */
    private function getClassMethodNode()
    {
        return $this->functionLikeNode;
    }

    /**
     * Has class been loaded by PHP.
     *
     * @return bool
     *     If class file with this method was included.
     */
    public function wasIncluded()
    {
        return
            interface_exists($this->className, false) ||
            trait_exists($this->className, false)     ||
            class_exists($this->className, false);
    }
}
