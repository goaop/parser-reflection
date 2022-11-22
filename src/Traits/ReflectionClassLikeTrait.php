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
use ReflectionClass as BaseReflectionClass;
use ReflectionClassConstant as BaseReflectionClassConstant;
use ReflectionException as BaseReflectionException;
use ReflectionExtension as BaseReflectionExtension;
use ReflectionMethod as BaseReflectionMethod;
use ReflectionObject as BaseReflectionObject;
use ReflectionProperty as BaseReflectionProperty;

/**
 * General class-like reflection
 *
 * @template T of object
 */
trait ReflectionClassLikeTrait
{
    use InitializationTrait;

    /**
     * @var ClassLike
     */
    protected ClassLike $classLikeNode;

    /**
     * Short name of the class, without namespace
     *
     * @var ?string
     */
    protected ?string $className = null;

    /**
     * List of all constants from the class
     *
     * @var array|null
     */
    protected ?array $constants = null;

    /**
     * Interfaces, empty array or null if not initialized yet
     *
     * @var BaseReflectionClass[]|array|null
     */
    protected ?array $interfaceClasses;

    /**
     * List of traits, empty array or null if not initialized yet
     *
     * @var  BaseReflectionClass[]|array|null
     */
    protected ?array $traits;

    /**
     * Additional list of trait adaptations
     *
     * @var TraitUseAdaptation[]|array
     */
    protected array $traitAdaptations;

    /**
     * @var array|ReflectionMethod[]
     */
    protected array $methods;

    /**
     * Namespace name
     *
     * @var string
     */
    protected string $namespaceName = '';

    /**
     * Parent class, or false if not present, null if uninitialized yet
     *
     * @var BaseReflectionClass|false|null
     */
    protected null|BaseReflectionClass|false $parentClass;

    /**
     * @var ReflectionProperty[]|null
     */
    protected ?array $properties = null;

    /**
     * @var array|ReflectionClassConstant[]
     */
    protected array $classConstants;

    /**
     * {@inheritDoc}
     */
    public function __toString(): string
    {
        $isObject = $this instanceof BaseReflectionObject;

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
    public function getConstant(string $name): mixed
    {
        if ($this->hasConstant($name)) {
            return $this->constants[$name];
        }

        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function getConstants(
        ?int $filter = ReflectionClassConstant::IS_PUBLIC
                      |ReflectionClassConstant::IS_PROTECTED
                      |ReflectionClassConstant::IS_PRIVATE
    ): array {
        if (!isset($this->constants)) {
            $this->constants = $this->recursiveCollect(
                function (array &$result, BaseReflectionClass $instance) {
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
    public function getConstructor(): ?BaseReflectionMethod
    {
        try {
            /** @noinspection PhpUnhandledExceptionInspection */
            return $this->getMethod('__construct');
        } catch (ReflectionException) {
            return null;
        }
    }

    /**
     * Gets default properties from a class (including inherited properties).
     *
     * @link http://php.net/manual/en/reflectionclass.getdefaultproperties.php
     *
     * @return array An array of default properties, with the key being the name of the property and the value being
     * the default value of the property or NULL if the property doesn't have a default value
     */
    public function getDefaultProperties(): array
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
                $isInternalReflection = get_class($property) === BaseReflectionProperty::class;

                if (!$isInternalReflection || $isStaticProperty) {
                    $defaultValues[$propertyName] = $property->getValue();
                } else {
                    // Internal reflection and dynamic property
                    $classProperties = $property->getDeclaringClass()
                                                ->getDefaultProperties();

                    $defaultValues[$propertyName] = $classProperties[$propertyName];
                }
            }
        }

        return $defaultValues;
    }

    /**
     * {@inheritDoc}
     */
    public function getDocComment(): string|false
    {
        $docComment = $this->classLikeNode->getDocComment();

        return $docComment ? $docComment->getText() : false;
    }

    /**
     * {@inheritDoc}
     */
    public function getEndLine(): int|false
    {
        return $this->classLikeNode->getAttribute('endLine');
    }

    /**
     * Gets the constructor of the class
     *
     * @link https://php.net/manual/en/reflectionclass.getconstructor.php
     *
     * @return ReflectionMethod|null A {@see ReflectionMethod} object reflecting
     *                               the class' constructor, or {@see null} if the class has no constructor.
     */
    public function getExtension(): ?BaseReflectionExtension
    {
        return null;
    }

    /**
     * {@inheritDoc}
     */
    public function getExtensionName(): string|false
    {
        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function getFileName(): string|false
    {
        return $this->classLikeNode->getAttribute('fileName');
    }

    /**
     * {@inheritDoc}
     */
    public function getInterfaceNames(): array
    {
        return array_keys($this->getInterfaces());
    }

    /**
     * {@inheritDoc}
     */
    public function getInterfaces(): array
    {
        if (!isset($this->interfaceClasses)) {
            $this->interfaceClasses = $this->recursiveCollect(
                function (array &$result, BaseReflectionClass $instance) {
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
     * Gets a <b>ReflectionMethod</b> for a class method.
     *
     * @link https://php.net/manual/en/reflectionclass.getmethod.php
     *
     * @param string $name The method name to reflect.
     *
     * @return ReflectionMethod A {@see ReflectionMethod}
     *
     * @throws ReflectionException if the method does not exist.
     */
    public function getMethod(string $name): BaseReflectionMethod
    {
        $methods = $this->getMethods();
        foreach ($methods as $method) {
            if ($method->getName() === $name) {
                return $method;
            }
        }

        throw new ReflectionException(sprintf('Method %s does not exist', $name));
    }

    /**
     * {@inheritDoc}
     */
    public function getMethods(?int $filter = null): array
    {
        if (!isset($this->methods)) {
            $directMethods = ReflectionMethod::collectFromClassNode($this->classLikeNode, $this);
            $parentMethods = $this->recursiveCollect(
                function (array &$result, BaseReflectionClass $instance, $isParent) {
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
     * {@inheritDoc}
     */
    public function getModifiers(): int
    {
        $this->initializeInternalReflection();

        return parent::getModifiers();
    }

    /**
     * {@inheritDoc}
     */
    public function getName(): string
    {
        $namespaceName = $this->namespaceName ? $this->namespaceName . '\\' : '';

        return $namespaceName . $this->getShortName();
    }

    /**
     * {@inheritDoc}
     */
    public function getNamespaceName(): string
    {
        return $this->namespaceName;
    }

    /**
     * Gets parent class
     *
     * @link https://php.net/manual/en/reflectionclass.getparentclass.php
     *
     * @return ReflectionClass|false A {@see ReflectionClass} or {@see false}
     *                               if there's no parent.
     */
    public function getParentClass(): BaseReflectionClass|false
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
     * {@inheritDoc}
     */
    public function getProperties(?int $filter = null): array
    {
        if (!isset($this->properties)) {
            $directProperties = ReflectionProperty::collectFromClassNode($this->classLikeNode, $this->getName());
            $parentProperties = $this->recursiveCollect(
                function (array &$result, BaseReflectionClass $instance, $isParent) {
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

        // Without filter, we can just return the full list
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
     * Gets a <b>ReflectionProperty</b> for a class's property
     *
     * @link https://php.net/manual/en/reflectionclass.getproperty.php
     *
     * @param string $name The property name.
     *
     * @return ReflectionProperty A {@see ReflectionProperty}
     *
     * @throws ReflectionException If no property exists by that name.
     */
    public function getProperty(string $name): BaseReflectionProperty
    {
        $properties = $this->getProperties();
        foreach ($properties as $property) {
            if ($property->getName() === $name) {
                return $property;
            }
        }

        throw new ReflectionException(sprintf('Property %s does not exist', $name));
    }

    /**
     * Gets a ReflectionClassConstant for a class's property
     *
     * @link https://php.net/manual/en/reflectionclass.getreflectionconstant.php
     *
     * @param string $name The class constant name.
     *
     * @return ReflectionClassConstant|false A {@see ReflectionClassConstant}.
     */
    public function getReflectionConstant(string $name): BaseReflectionClassConstant|false
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
     * {@inheritDoc}
     */
    public function getReflectionConstants(
        ?int $filter = ReflectionClassConstant::IS_PUBLIC
                      |ReflectionClassConstant::IS_PROTECTED
                      |ReflectionClassConstant::IS_PRIVATE
    ): array {
        if (!isset($this->classConstants)) {
            $directClassConstants = ReflectionClassConstant::collectFromClassNode(
                $this->classLikeNode,
                $this->getName()
            );
            $parentClassConstants = $this->recursiveCollect(
                function (array &$result, BaseReflectionClass $instance, $isParent) {
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
    public function getShortName(): string
    {
        return $this->className;
    }

    /**
     * {@inheritDoc}
     */
    public function getStartLine(): int|false
    {
        return $this->classLikeNode->getAttribute('startLine');
    }

    /**
     * {@inheritDoc}
     */
    public function getTraitAliases(): array
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
     * {@inheritDoc}
     */
    public function getTraitNames(): array
    {
        return array_keys($this->getTraits());
    }

    /**
     * {@inheritDoc}
     */
    public function getTraits(): array
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
    public function hasConstant(string $name): bool
    {
        $constants   = $this->getConstants();
        $hasConstant = isset($constants[$name]) || array_key_exists($name, $constants);

        return $hasConstant;
    }

    /**
     * {@inheritDoc}
     */
    public function hasMethod(string $name): bool
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
     * {@inheritDoc}
     */
    public function hasProperty(string $name): bool
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
     * Checks whether it implements an interface.
     *
     * @link https://php.net/manual/en/reflectionclass.implementsinterface.php
     *
     * @param ReflectionClass|string $interface The interface name.
     *
     * @return bool Returns {@see true} on success or {@see false} on failure.
     */
    public function implementsInterface(BaseReflectionClass|string $interface): bool
    {
        $allInterfaces = $this->getInterfaces();

        if ($interface instanceof BaseReflectionClass) {
            $interface = $interface->getName();
        }

        return isset($allInterfaces[$interface]);
    }

    /**
     * {@inheritDoc}
     */
    public function inNamespace(): bool
    {
        return !empty($this->namespaceName);
    }

    /**
     * {@inheritDoc}
     */
    public function isAbstract(): bool
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
    public function isAnonymous(): bool
    {
        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function isCloneable(): bool
    {
        if ($this->isInterface() || $this->isTrait() || $this->isAbstract()) {
            return false;
        }

        if ($this->hasMethod('__clone')) {
            /** @noinspection PhpUnhandledExceptionInspection */
            return $this->getMethod('__clone')
                        ->isPublic();
        }

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function isFinal(): bool
    {
        $isFinal = $this->classLikeNode instanceof Class_ && $this->classLikeNode->isFinal();

        return $isFinal;
    }

    /**
     * {@inheritDoc}
     */
    public function isInstance(object $object): bool
    {
        $className = $this->getName();

        return $className === get_class($object) || is_subclass_of($object, $className);
    }

    /**
     * {@inheritDoc}
     */
    public function isInstantiable(): bool
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
    public function isInterface(): bool
    {
        return ($this->classLikeNode instanceof Interface_);
    }

    /**
     * {@inheritDoc}
     */
    public function isInternal(): bool
    {
        // never can be an internal method
        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function isIterateable(): bool
    {
        return $this->implementsInterface('Traversable');
    }

    /**
     * Checks if a subclass
     *
     * @link https://php.net/manual/en/reflectionclass.issubclassof.php
     *
     * @param string|ReflectionClass $class Either the name of the class as string or a {@see ReflectionClass}
     *                                      object of the class to check against.
     *
     * @return bool {@see true} on success or {@see false} on failure.
     */
    public function isSubclassOf(BaseReflectionClass|string $class): bool
    {
        if ($class instanceof ReflectionClass) {
            $className = $class->name;
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

        /** @noinspection PhpTernaryExpressionCanBeReplacedWithConditionInspection */
        return $parent === false ? false : $parent->isSubclassOf($class);
    }

    /**
     * {@inheritDoc}
     */
    public function isTrait(): bool
    {
        return ($this->classLikeNode instanceof Trait_);
    }

    /**
     * {@inheritDoc}
     */
    public function isUserDefined(): bool
    {
        // always defined by user, because we parse the source code
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function getStaticProperties(): ?array
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
     * @link https://php.net/manual/en/reflectionclass.getstaticpropertyvalue.php
     * @param string $name    The name of the static property for which to return a value.
     * @param mixed  $default A default value to return in case the class does
     *                        not declare a static property with the given name. If the property does
     *                        not exist and this argument is omitted, a {@see ReflectionException} is thrown.
     *
     * @return mixed The value of the static property.
     *
     * @noinspection PhpDocMissingThrowsInspection
     */
    public function getStaticPropertyValue(string $name, mixed $default = null): mixed
    {
        $properties     = $this->getStaticProperties();
        $propertyExists = array_key_exists($name, $properties);

        if (!$propertyExists && func_num_args() === 1) {
            /** @noinspection PhpUnhandledExceptionInspection */
            throw new ReflectionException("Static property does not exist and no default value is given");
        }

        return $propertyExists ? $properties[$name] : $default;
    }


    /**
     * Creates a new class instance without invoking the constructor.
     *
     * @link https://php.net/manual/en/reflectionclass.newinstancewithoutconstructor.php
     *
     * @return T a new instance of the class.
     *
     * @throws ReflectionException if the class is an internal class that
     *                             cannot be instantiated without invoking the constructor. In PHP 5.6.0
     *                             onwards, this exception is limited only to internal classes that are final.
     */
    public function newInstance(...$args)
    {
        $this->initializeInternalReflection();

        try {
            return parent::newInstance(...$args);
        } catch (BaseReflectionException $e) {
            throw new ReflectionException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Creates a new class instance from given arguments.
     *
     * @link https://php.net/manual/en/reflectionclass.newinstanceargs.php
     *
     * @param array $args The parameters to be passed to the class constructor as an array.
     *
     * @return T|null a new instance of the class.
     *
     * @throws ReflectionException if the class constructor is not public or if
     *                             the class does not have a constructor and the $args parameter contains
     *                             one or more parameters.
     */
    public function newInstanceArgs(array $args = []): ?object
    {
        $function = __FUNCTION__;
        $this->initializeInternalReflection();

        try {
            return parent::$function($args);
        } catch (BaseReflectionException $e) {
            throw new ReflectionException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Creates a new class instance without invoking the constructor.
     *
     * @link https://php.net/manual/en/reflectionclass.newinstancewithoutconstructor.php
     *
     * @return T a new instance of the class.
     *
     * @throws ReflectionException if the class is an internal class that
     *                             cannot be instantiated without invoking the constructor. In PHP 5.6.0
     *                             onwards, this exception is limited only to internal classes that are final.
     */
    public function newInstanceWithoutConstructor(): object
    {
        $function = __FUNCTION__;
        $this->initializeInternalReflection();

        try {
            return parent::$function();
        } catch (BaseReflectionException $e) {
            throw new ReflectionException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function setStaticPropertyValue(string $name, mixed $value): void
    {
        $this->initializeInternalReflection();

        parent::setStaticPropertyValue($name, $value);
    }

    private function recursiveCollect(Closure $collector): array
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
     *
     * @return void
     */
    private function collectSelfConstants(): void
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
     * @param string $className The name of the class to create a reflection for.
     *
     * @return BaseReflectionClass The appropriate reflection object.
     *
     * @throws ReflectionException if the class does not exist.
     */
    abstract protected function createReflectionForClass(string $className): BaseReflectionClass;
}
