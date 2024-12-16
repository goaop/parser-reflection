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

namespace Go\ParserReflection\Traits;

use Closure;
use Go\ParserReflection\ReflectionClass;
use Go\ParserReflection\ReflectionClassConstant;
use Go\ParserReflection\ReflectionException;
use Go\ParserReflection\ReflectionMethod;
use Go\ParserReflection\ReflectionProperty;
use Go\ParserReflection\ValueResolver\NodeExpressionResolver;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassConst;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\Trait_;
use PhpParser\Node\Stmt\TraitUseAdaptation;
use ReflectionObject;
use RuntimeException;

use function func_num_args;

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
     * List of all constants from the class
     *
     * @var array
     */
    protected $constants;

    /**
     * Interfaces, empty array or null if not initialized yet
     *
     * @var \ReflectionClass[]|array|null
     */
    protected $interfaceClasses;

    /**
     * List of traits, empty array or null if not initialized yet
     *
     * @var  \ReflectionClass[]|array|null
     */
    protected $traits;

    /**
     * Additional list of trait adaptations
     *
     * @var TraitUseAdaptation[]|array
     */
    protected $traitAdaptations;

    /**
     * @var array|ReflectionMethod[]
     */
    protected $methods;

    /**
     * Namespace name
     *
     * @var string
     */
    protected $namespaceName = '';

    /**
     * Parent class, or false if not present, null if uninitialized yet
     *
     * @var \ReflectionClass|false|null
     */
    protected $parentClass;

    /**
     * @var array|ReflectionProperty[]
     */
    protected $properties;

    /**
     * @var array|ReflectionClassConstant[]
     */
    protected $classConstants;

    /**
     * Returns the string representation of the ReflectionClass object.
     *
     * @return string
     */
    public function __toString()
    {
        $isObject = $this instanceof ReflectionObject;

        $staticProperties = $staticMethods = $defaultProperties = $dynamicProperties = $methods = [];

        $format = "%s [ <user> %sclass %s%s%s ] {\n";
        $format .= "  @@ %s %d-%d\n\n";
        $format .= "  - Constants [%d] {%s\n  }\n\n";
        $format .= "  - Static properties [%d] {%s\n  }\n\n";
        $format .= "  - Static methods [%d] {%s\n  }\n\n";
        $format .= "  - Properties [%d] {%s\n  }\n\n";
        $format .= ($isObject ? "  - Dynamic properties [%d] {%s\n  }\n\n" : '%s%s');
        $format .= "  - Methods [%d] {%s\n  }\n";
        $format .= "}\n";

        foreach ($this->getProperties() as $property) {
            if ($property->isStatic()) {
                $staticProperties[] = $property;
            } elseif ($property->isDefault()) {
                $defaultProperties[] = $property;
            } else {
                $dynamicProperties[] = $property;
            }
        }

        foreach ($this->getMethods() as $method) {
            if ($method->isStatic()) {
                $staticMethods[] = $method;
            } else {
                $methods[] = $method;
            }
        }

        $buildString = static function (array $items, $indentLevel = 4) {
            if (!count($items)) {
                return '';
            }
            $indent = "\n" . str_repeat(' ', $indentLevel);

            return $indent . implode($indent, explode("\n", implode("\n", $items)));
        };

        $buildConstants = static function (array $items, $indentLevel = 4) {
            $str = '';
            foreach ($items as $name => $value) {
                $str .= "\n" . str_repeat(' ', $indentLevel);
                $str .= sprintf(
                    'Constant [ %s %s ] { %s }',
                    gettype($value),
                    $name,
                    $value
                );
            }

            return $str;
        };
        $interfaceNames = $this->getInterfaceNames();
        $parentClass    = $this->getParentClass();
        $modifiers      = '';
        if ($this->isAbstract()) {
            $modifiers = 'abstract ';
        } elseif ($this->isFinal()) {
            $modifiers = 'final ';
        }

        $string = sprintf(
            $format,
            ($isObject ? 'Object of class' : 'Class'),
            $modifiers,
            $this->getName(),
            false !== $parentClass ? (' extends ' . $parentClass->getName()) : '',
            $interfaceNames ? (' implements ' . implode(', ', $interfaceNames)) : '',
            $this->getFileName(),
            $this->getStartLine(),
            $this->getEndLine(),
            count($this->getConstants()),
            $buildConstants($this->getConstants()),
            count($staticProperties),
            $buildString($staticProperties),
            count($staticMethods),
            $buildString($staticMethods),
            count($defaultProperties),
            $buildString($defaultProperties),
            $isObject ? count($dynamicProperties) : '',
            $isObject ? $buildString($dynamicProperties) : '',
            count($methods),
            $buildString($methods)
        );

        return $string;
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
    public function getConstants(?int $filter = null): array
    {
        if (!isset($this->constants)) {
            $this->constants = $this->recursiveCollect(
                function (array &$result, \ReflectionClass $instance) {
                    $result += $instance->getConstants();
                }
            );
            $this->collectSelfConstants();
        }

        return $this->constants;
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
     * Gets default properties from a class (including inherited properties).
     *
     * @link http://php.net/manual/en/reflectionclass.getdefaultproperties.php
     *
     * @return array An array of default properties, with the key being the name of the property and the value being
     * the default value of the property or NULL if the property doesn't have a default value
     */
    public function getDefaultProperties()
    {
        $defaultValues = [];
        $properties    = $this->getProperties();
        $staticOrder   = [true, false];
        foreach ($staticOrder as $shouldBeStatic) {
            foreach ($properties as $property) {
                $isStaticProperty = $property->isStatic();
                if ($shouldBeStatic !== $isStaticProperty) {
                    continue;
                }
                $propertyName         = $property->getName();
                $isInternalReflection = get_class($property) === \ReflectionProperty::class;

                if (!$isInternalReflection || $isStaticProperty) {
                    $defaultValues[$propertyName] = $property->getValue();
                } elseif (!$isStaticProperty) {
                    // Internal reflection and dynamic property
                    $classProperties = $property->getDeclaringClass()
                                                ->getDefaultProperties()
                    ;

                    $defaultValues[$propertyName] = $classProperties[$propertyName];
                }
            }
        }

        return $defaultValues;
    }

    /**
     * {@inheritDoc}
     */
    public function getDocComment()
    {
        $docComment = $this->classLikeNode->getDocComment();

        return $docComment ? $docComment->getText() : false;
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
        return false;
    }

    public function getFileName()
    {
        return $this->classLikeNode->getAttribute('fileName');
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
    public function getInterfaces()
    {
        if (!isset($this->interfaceClasses)) {
            $this->interfaceClasses = $this->recursiveCollect(
                function (array &$result, \ReflectionClass $instance) {
                    if ($instance->isInterface()) {
                        $result[$instance->name] = $instance;
                    }
                    $result += $instance->getInterfaces();
                }
            );
        }

        return $this->interfaceClasses;
    }

    /**
     * {@inheritdoc}
     * @param string $name
     */
    public function getMethod($name)
    {
        $methods = $this->getMethods();
        foreach ($methods as $method) {
            if ($method->getName() === $name) {
                return $method;
            }
        }

        return false;
    }

    /**
     * Returns list of reflection methods
     *
     * @param null|int $filter Optional filter
     *
     * @return array|\ReflectionMethod[]
     */
    public function getMethods($filter = null)
    {
        if (!isset($this->methods)) {
            $directMethods = ReflectionMethod::collectFromClassNode($this->classLikeNode, $this);
            $parentMethods = $this->recursiveCollect(
                function (array &$result, \ReflectionClass $instance, $isParent) {
                    $reflectionMethods = [];
                    foreach ($instance->getMethods() as $reflectionMethod) {
                        if (!$isParent || !$reflectionMethod->isPrivate()) {
                            $reflectionMethods[$reflectionMethod->name] = $reflectionMethod;
                        }
                    }
                    $result += $reflectionMethods;
                }
            );
            $methods       = $directMethods + $parentMethods;

            $this->methods = $methods;
        }
        if (!isset($filter)) {
            return array_values($this->methods);
        }

        $methods = [];
        foreach ($this->methods as $method) {
            if (!($filter & $method->getModifiers())) {
                continue;
            }
            $methods[] = $method;
        }

        return $methods;
    }

    /**
     * Returns a bitfield of the access modifiers for this class.
     *
     * @link http://php.net/manual/en/reflectionclass.getmodifiers.php
     *
     * NB: this method is not fully compatible with original value because of hidden internal constants
     *
     * @return int
     */
    public function getModifiers()
    {
        $modifiers = 0;

        if ($this->isFinal()) {
            $modifiers += \ReflectionClass::IS_FINAL;
        }

        if (PHP_VERSION_ID < 70000 && $this->isTrait()) {
            $modifiers += \ReflectionClass::IS_EXPLICIT_ABSTRACT;
        }

        if ($this->classLikeNode instanceof Class_ && $this->classLikeNode->isAbstract()) {
            $modifiers += \ReflectionClass::IS_EXPLICIT_ABSTRACT;
        }

        if ($this->isInterface()) {
            $abstractMethods = $this->getMethods();
        } else {
            $abstractMethods = $this->getMethods(\ReflectionMethod::IS_ABSTRACT);
        }
        if (!empty($abstractMethods)) {
            $modifiers += \ReflectionClass::IS_IMPLICIT_ABSTRACT;
        }

        return $modifiers;
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
    public function getNamespaceName()
    {
        return $this->namespaceName;
    }

    /**
     * {@inheritDoc}
     */
    public function getParentClass()
    {
        if (!isset($this->parentClass)) {
            static $extendsField = 'extends';

            $parentClass = false;
            $hasExtends  = in_array($extendsField, $this->classLikeNode->getSubNodeNames(), true);
            $extendsNode = $hasExtends ? $this->classLikeNode->$extendsField : null;
            if ($extendsNode instanceof FullyQualified) {
                $extendsName = $extendsNode->toString();
                $parentClass = $this->createReflectionForClass($extendsName);
            }
            $this->parentClass = $parentClass;
        }

        return $this->parentClass;
    }

    /**
     * Retrieves reflected properties.
     *
     * @param int $filter The optional filter, for filtering desired property types.
     *                    It's configured using the ReflectionProperty constants, and defaults to all property types.
     *
     * @return ReflectionProperty[]
     */
    public function getProperties($filter = null)
    {
        if (!isset($this->properties)) {
            $directProperties = ReflectionProperty::collectFromClassNode($this->classLikeNode, $this->getName());
            $parentProperties = $this->recursiveCollect(
                function (array &$result, \ReflectionClass $instance, $isParent) {
                    $reflectionProperties = [];
                    foreach ($instance->getProperties() as $reflectionProperty) {
                        if (!$isParent || !$reflectionProperty->isPrivate()) {
                            $reflectionProperties[$reflectionProperty->name] = $reflectionProperty;
                        }
                    }
                    $result += $reflectionProperties;
                }
            );
            $properties       = $directProperties + $parentProperties;

            $this->properties = $properties;
        }

        // Without filter we can just return the full list
        if (!isset($filter)) {
            return array_values($this->properties);
        }

        $properties = [];
        foreach ($this->properties as $property) {
            if (!($filter & $property->getModifiers())) {
                continue;
            }
            $properties[] = $property;
        }

        return $properties;
    }

    /**
     * {@inheritdoc}
     */
    public function getProperty($name)
    {
        $properties = $this->getProperties();
        foreach ($properties as $property) {
            if ($property->getName() === $name) {
                return $property;
            }
        }

        return false;
    }

    /**
     * @inheritDoc
     */
    public function getReflectionConstant($name)
    {
        $classConstants = $this->getReflectionConstants();
        foreach ($classConstants as $classConstant) {
            if ($classConstant->getName() === $name) {
                return $classConstant;
            }
        }

        return false;
    }

    /**
     * @inheritDoc
     */
    public function getReflectionConstants(?int $filter = null): array
    {
        if (!isset($this->classConstants)) {
            $directClassConstants = ReflectionClassConstant::collectFromClassNode(
                $this->classLikeNode,
                $this->getName()
            );
            $parentClassConstants = $this->recursiveCollect(
                function (array &$result, \ReflectionClass $instance, $isParent) {
                    $reflectionClassConstants = [];
                    foreach ($instance->getReflectionConstants() as $reflectionClassConstant) {
                        if (!$isParent || !$reflectionClassConstant->isPrivate()) {
                            $reflectionClassConstants[$reflectionClassConstant->name] = $reflectionClassConstant;
                        }
                    }
                    $result += $reflectionClassConstants;
                }
            );
            $classConstants       = $directClassConstants + $parentClassConstants;

            $this->classConstants = $classConstants;
        }

        return array_values($this->classConstants);
    }

    /**
     * {@inheritDoc}
     */
    public function getShortName()
    {
        return $this->className;
    }

    public function getStartLine()
    {
        return $this->classLikeNode->getAttribute('startLine');
    }

    /**
     * Returns an array of trait aliases
     *
     * @link http://php.net/manual/en/reflectionclass.gettraitaliases.php
     *
     * @return array|null an array with new method names in keys and original names (in the format
     *                    "TraitName::original") in values.
     */
    public function getTraitAliases()
    {
        $aliases = [];
        $traits  = $this->getTraits();
        foreach ($this->traitAdaptations as $adaptation) {
            if ($adaptation instanceof TraitUseAdaptation\Alias) {
                $methodName = $adaptation->method;
                $traitName  = null;
                foreach ($traits as $trait) {
                    if ($trait->hasMethod($methodName)) {
                        $traitName = $trait->getName();
                        break;
                    }
                }
                $aliases[$adaptation->newName] = $traitName . '::' . $methodName;
            }
        }

        return $aliases;
    }

    /**
     * Returns an array of names of traits used by this class
     *
     * @link http://php.net/manual/en/reflectionclass.gettraitnames.php
     *
     * @return array
     */
    public function getTraitNames()
    {
        return array_keys($this->getTraits());
    }

    /**
     * Returns an array of traits used by this class
     *
     * @link http://php.net/manual/en/reflectionclass.gettraits.php
     *
     * @return array|\ReflectionClass[]
     */
    public function getTraits()
    {
        if (!isset($this->traits)) {
            $traitAdaptations       = [];
            $this->traits           = ReflectionClass::collectTraitsFromClassNode(
                $this->classLikeNode,
                $traitAdaptations
            );
            $this->traitAdaptations = $traitAdaptations;
        }

        return $this->traits;
    }

    /**
     * {@inheritDoc}
     */
    public function hasConstant($name)
    {
        $constants   = $this->getConstants();
        $hasConstant = isset($constants[$name]) || array_key_exists($name, $constants);

        return $hasConstant;
    }

    /**
     * {@inheritdoc}
     * @param string $name
     */
    public function hasMethod($name)
    {
        $methods = $this->getMethods();
        foreach ($methods as $method) {
            if ($method->getName() === $name) {
                return true;
            }
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function hasProperty($name)
    {
        $properties = $this->getProperties();
        foreach ($properties as $property) {
            if ($property->getName() === $name) {
                return true;
            }
        }

        return false;
    }

    /**
     * {@inheritDoc}
     * @param string $interfaceName
     */
    public function implementsInterface($interfaceName)
    {
        $allInterfaces = $this->getInterfaces();

        return isset($allInterfaces[$interfaceName]);
    }

    /**
     * {@inheritDoc}
     */
    public function inNamespace()
    {
        return !empty($this->namespaceName);
    }

    /**
     * {@inheritDoc}
     */
    public function isAbstract()
    {
        if ($this->classLikeNode instanceof Class_ && $this->classLikeNode->isAbstract()) {
            return true;
        }

        if ($this->isInterface() && !empty($this->getMethods())) {
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
    public function isCloneable()
    {
        if ($this->isInterface() || $this->isTrait() || $this->isAbstract()) {
            return false;
        }

        if ($this->hasMethod('__clone')) {
            return $this->getMethod('__clone')
                        ->isPublic()
                ;
        }

        return true;
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
            throw new RuntimeException(sprintf('Parameter must be an object, "%s" provided.', gettype($object)));
        }

        $className = $this->getName();

        return $className === get_class($object) || is_subclass_of($object, $className);
    }

    /**
     * {@inheritDoc}
     */
    public function isInstantiable()
    {
        if ($this->isInterface() || $this->isTrait() || $this->isAbstract()) {
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
    public function isInterface()
    {
        return ($this->classLikeNode instanceof Interface_);
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
    public function isIterateable()
    {
        return $this->implementsInterface('Traversable');
    }

    /**
     * {@inheritDoc}
     */
    public function isSubclassOf($class)
    {
        if (is_object($class)) {
            if ($class instanceof ReflectionClass) {
                $className = $class->name;
            } else {
                $className = get_class($class);
            }
        } else {
            $className = $class;
        }

        if (!$this->classLikeNode instanceof Class_) {
            return false;
        }

        $extends = $this->classLikeNode->extends;
        if ($extends && $extends->toString() === $className) {
            return true;
        }

        $parent = $this->getParentClass();

        return false === $parent ? false : $parent->isSubclassOf($class);
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
    public function isUserDefined()
    {
        // always defined by user, because we parse the source code
        return true;
    }

    /**
     * Gets static properties
     *
     * @link http://php.net/manual/en/reflectionclass.getstaticproperties.php
     *
     * @return array
     */
    public function getStaticProperties()
    {
        // In runtime static properties can be changed in any time
        if ($this->__isInitialized()) {
            return parent::getStaticProperties();
        }

        $properties = [];

        $reflectionProperties = $this->getProperties(ReflectionProperty::IS_STATIC);
        foreach ($reflectionProperties as $reflectionProperty) {
            if (!$reflectionProperty instanceof ReflectionProperty && !$reflectionProperty->isPublic()) {
                $reflectionProperty->setAccessible(true);
            }
            $properties[$reflectionProperty->getName()] = $reflectionProperty->getValue();
        }

        return $properties;
    }

    /**
     * Gets static property value
     *
     * @param string $name    The name of the static property for which to return a value.
     * @param mixed  $default A default value to return in case the class does not declare
     *                        a static property with the given name
     *
     * @return mixed
     * @throws ReflectionException If there is no such property and no default value was given
     */
    public function getStaticPropertyValue($name, $default = null)
    {
        $properties     = $this->getStaticProperties();
        $propertyExists = array_key_exists($name, $properties);

        if (!$propertyExists && func_num_args() === 1) {
            throw new ReflectionException("Static property does not exist and no default value is given");
        }

        return $propertyExists ? $properties[$name] : $default;
    }


    /**
     * Creates a new class instance from given arguments.
     *
     * @link http://php.net/manual/en/reflectionclass.newinstance.php
     *
     * Signature was hacked to support both 5.6, 7.1.x and 7.2.0 versions
     * @see  https://3v4l.org/hW9O9
     * @see  https://3v4l.org/sWT3j
     * @see  https://3v4l.org/eeVf8
     *
     * @param mixed $arg  First argument
     * @param mixed $args Accepts a variable number of arguments which are passed to the class constructor
     *
     * @return object
     */
    public function newInstance($arg = null, ...$args)
    {
        $args = array_slice(array_merge([$arg], $args), 0, func_num_args());
        $this->initializeInternalReflection();

        return parent::newInstance(...$args);
    }

    /**
     * Creates a new class instance from given arguments.
     *
     * @link http://php.net/manual/en/reflectionclass.newinstanceargs.php
     *
     * @param array $args The parameters to be passed to the class constructor as an array.
     *
     * @return object
     */
    public function newInstanceArgs(array $args = [])
    {
        $function = __FUNCTION__;
        $this->initializeInternalReflection();

        return parent::$function($args);
    }

    /**
     * Creates a new class instance without invoking the constructor.
     *
     * @link http://php.net/manual/en/reflectionclass.newinstancewithoutconstructor.php
     *
     * @return object
     */
    public function newInstanceWithoutConstructor($args = null)
    {
        $function = __FUNCTION__;
        $this->initializeInternalReflection();

        return parent::$function($args);
    }

    /**
     * Sets static property value
     *
     * @link http://php.net/manual/en/reflectionclass.setstaticpropertyvalue.php
     *
     * @param string $name  Property name
     * @param mixed  $value New property value
     */
    public function setStaticPropertyValue($name, $value)
    {
        $this->initializeInternalReflection();

        parent::setStaticPropertyValue($name, $value);
    }

    private function recursiveCollect(Closure $collector)
    {
        $result   = [];
        $isParent = true;

        $traits = $this->getTraits();
        foreach ($traits as $trait) {
            $collector($result, $trait, !$isParent);
        }

        $parentClass = $this->getParentClass();
        if ($parentClass) {
            $collector($result, $parentClass, $isParent);
        }

        $interfaces = ReflectionClass::collectInterfacesFromClassNode($this->classLikeNode);
        foreach ($interfaces as $interface) {
            $collector($result, $interface, $isParent);
        }

        return $result;
    }

    /**
     * Collects list of constants from the class itself
     */
    private function collectSelfConstants()
    {
        $expressionSolver = new NodeExpressionResolver($this);
        $localConstants   = [];

        // constants can be only top-level nodes in the class, so we can scan them directly
        foreach ($this->classLikeNode->stmts as $classLevelNode) {
            if ($classLevelNode instanceof ClassConst) {
                $nodeConstants = $classLevelNode->consts;
                if (!empty($nodeConstants)) {
                    foreach ($nodeConstants as $nodeConstant) {
                        $expressionSolver->process($nodeConstant->value);
                        $localConstants[$nodeConstant->name->toString()] = $expressionSolver->getValue();

                        $this->constants = $localConstants + $this->constants;
                    }
                }
            }
        }
    }

    /**
     * Create a ReflectionClass for a given class name.
     *
     * @param string $className
     *     The name of the class to create a reflection for.
     *
     * @return ReflectionClass
     *     The appropriate reflection object.
     */
    abstract protected function createReflectionForClass(string $className);
}
