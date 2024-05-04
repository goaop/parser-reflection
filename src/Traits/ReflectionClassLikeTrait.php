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
use Go\ParserReflection\Resolver\NodeExpressionResolver;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassConst;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Enum_;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\Trait_;
use PhpParser\Node\Stmt\TraitUseAdaptation;
use ReflectionExtension;
use ReflectionObject;
use RuntimeException;

use function func_num_args;

/**
 * General class-like reflection
 */
trait ReflectionClassLikeTrait
{
    use InitializationTrait;

    protected ClassLike $classLikeNode;

    /**
     * Short name of the class, without namespace
     */
    protected string $className;

    /**
     * List of all constants from the class or null if not initialized yet
     */
    protected ?array $constants;

    /**
     * Interfaces or null if not initialized yet
     *
     * @var \ReflectionClass[]|null
     */
    protected ?array $interfaceClasses;

    /**
     * List of traits or null if not initialized yet
     *
     * @var  \ReflectionClass[]|null
     */
    protected ?array $traits;

    /**
     * Additional list of trait adaptations
     *
     * @var TraitUseAdaptation[]
     */
    protected array $traitAdaptations = [];

    /**
     * @var ReflectionMethod[]
     */
    protected ?array $methods;

    /**
     * Namespace name
     */
    protected string $namespaceName = '';

    /**
     * Parent class, or false if not present, null if uninitialized yet
     */
    protected null|\ReflectionClass|false $parentClass;

    /**
     * @var ReflectionProperty[]
     */
    protected ?array $properties;

    /**
     * @var ReflectionClassConstant[]
     */
    protected ?array $classConstants;

    /**
     * Returns the string representation of the ReflectionClass object.
     */
    public function __toString(): string
    {
        $isObject = $this instanceof ReflectionObject;

        $staticProperties = $staticMethods = $defaultProperties = $dynamicProperties = $methods = [];

        $format = "%s%s [ <user> %sclass %s%s%s ] {\n";
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
            $this->getDocComment() ? $this->getDocComment() . "\n" : '',
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
    public function getConstructor(): ?ReflectionMethod
    {
        try {
            $constructor = $this->getMethod('__construct');
        } catch (\ReflectionException) {
            $constructor = null;
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
                $isInternalReflection = $property::class === \ReflectionProperty::class;

                if (!$property->hasDefaultValue()) {
                    continue;
                }

                if (!$isInternalReflection || $isStaticProperty) {
                    $defaultValues[$propertyName] = $property->getValue();
                } elseif (!$isStaticProperty) {
                    // Internal reflection and dynamic property
                    $classProperties = $property->getDeclaringClass()->getDefaultProperties();

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

    public function getEndLine(): int|false
    {
        return $this->classLikeNode->getAttribute('endLine');
    }

    public function getExtension(): ?ReflectionExtension
    {
        return null;
    }

    public function getExtensionName(): string|false
    {
        return false;
    }

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
     *
     * @return ReflectionMethod
     */
    public function getMethod(string $name): \ReflectionMethod
    {
        foreach ($this->getMethods() as $method) {
            if ($method->getName() === $name) {
                return $method;
            }
        }

        throw new ReflectionException("Method " . $this->getName() . "::" . $name . " does not exist");
    }

    /**
     * {@inheritdoc}
     *
     * @return ReflectionMethod[]
     */
    public function getMethods(int|null $filter = null): array
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
            $methods = $directMethods + $parentMethods;

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
     */
    public function getModifiers(): int
    {
        $modifiers = 0;

        if ($this->isFinal()) {
            $modifiers += \ReflectionClass::IS_FINAL;
        }

        if ($this->isReadOnly()) {
            $modifiers += \ReflectionClass::IS_READONLY;
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
     * {@inheritDoc}
     */
    public function getParentClass(): \ReflectionClass|false
    {
        if (!isset($this->parentClass)) {

            $parentClass = false;
            $extendsNode = $this->classLikeNode->extends ?? null;

            if ($extendsNode instanceof Name && $extendsNode->getAttribute('resolvedName') instanceof FullyQualified) {
                $extendsName = $extendsNode->getAttribute('resolvedName')->toString();
                $parentClass = $this->createReflectionForClass($extendsName);
            }
            $this->parentClass = $parentClass;
        }

        return $this->parentClass;
    }

    /**
     * Retrieves reflected properties.
     *
     * @inheritDoc
     *
     * @return ReflectionProperty[]
     */
    public function getProperties(int|null $filter = null): array
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
            $properties = $directProperties + $parentProperties;

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
    public function getProperty(string $name): \ReflectionProperty
    {
        $properties = $this->getProperties();
        foreach ($properties as $property) {
            if ($property->getName() === $name) {
                return $property;
            }
        }

        throw new ReflectionException("Property " . $this->getName() . "::" . $name . " does not exist");
    }

    /**
     * @inheritDoc
     */
    public function getReflectionConstant(string $name): \ReflectionClassConstant|false
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
            $classConstants = $directClassConstants + $parentClassConstants;

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

    public function getStartLine(): int|false
    {
        if ($this->classLikeNode->attrGroups !== []) {
            $attrGroups = $this->classLikeNode->attrGroups;
            $lastAttrGroupsEndLine = end($attrGroups)->getAttribute('endLine');

            return $lastAttrGroupsEndLine + 1;
        }

        return $this->classLikeNode->getAttribute('startLine');
    }

    /**
     * Returns an array of trait aliases
     *
     * @link http://php.net/manual/en/reflectionclass.gettraitaliases.php
     *
     * @return array an array with new method names in keys and original names (in the format
     *                    "TraitName::original") in values.
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
     * Returns an array of names of traits used by this class
     *
     * @link http://php.net/manual/en/reflectionclass.gettraitnames.php
     */
    public function getTraitNames(): array
    {
        return array_keys($this->getTraits());
    }

    /**
     * Returns an array of traits used by this class
     *
     * @link http://php.net/manual/en/reflectionclass.gettraits.php
     *
     * @return \ReflectionClass[]
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
        $constants = $this->getConstants();

        return isset($constants[$name]) || array_key_exists($name, $constants);
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
     * {@inheritDoc}
     */
    public function implementsInterface(\ReflectionClass|string $interfaceName): bool
    {
        $allInterfaces = $this->getInterfaces();

        if ($interfaceName instanceof \ReflectionClass) {
            return isset($allInterfaces[$interfaceName->getName()]);
        } else {
            return isset($allInterfaces[$interfaceName]);
        }
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
        if ($this->isInterface() || $this->isTrait() || $this->isAbstract() || $this->isEnum()) {
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
    public function isFinal(): bool
    {
        $isFinalClass = $this->classLikeNode instanceof Class_ && $this->classLikeNode->isFinal();

        return $isFinalClass || $this->isEnum();
    }

    /**
     * {@inheritDoc}
     */
    public function isReadOnly(): bool
    {
        return $this->classLikeNode instanceof Class_ && $this->classLikeNode->isReadonly();
    }

    /**
     * {@inheritDoc}
     */
    public function isInstance(object $object): bool
    {
        $className = $this->getName();

        return $className === $object::class || is_subclass_of($object, $className);
    }

    /**
     * {@inheritDoc}
     */
    public function isInstantiable(): bool
    {
        if ($this->isInterface() || $this->isTrait() || $this->isAbstract() || $this->isEnum()) {
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
        return $this->classLikeNode instanceof Interface_;
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
        return $this->isIterable();
    }

    /**
     * {@inheritDoc}
     */
    public function isIterable(): bool
    {
        return $this->implementsInterface('Traversable');
    }

    /**
     * {@inheritDoc}
     */
    public function isSubclassOf(\ReflectionClass|string $class): bool
    {
        if ($class instanceof \ReflectionClass) {
            $className = $class->name;
        } else {
            $className = $class;
        }

        if (!$this->classLikeNode instanceof Class_) {
            return false;
        }

        $extends = $this->classLikeNode->extends ?? null;
        if ($extends && $extends->toString() === $className) {
            return true;
        }

        $parent = $this->getParentClass();

        return false === $parent ? false : $parent->isSubclassOf($class);
    }

    /**
     * {@inheritDoc}
     */
    public function isEnum(): bool
    {
        return $this->classLikeNode instanceof Enum_;
    }

    /**
     * {@inheritDoc}
     */
    public function isTrait(): bool
    {
        return $this->classLikeNode instanceof Trait_;
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
     * Gets static properties
     *
     * @link http://php.net/manual/en/reflectionclass.getstaticproperties.php
     */
    public function getStaticProperties(): array
    {
        // In runtime static properties can be changed in any time
        if ($this->__isInitialized()) {
            return parent::getStaticProperties();
        }

        $properties = [];

        $reflectionProperties = $this->getProperties(\ReflectionProperty::IS_STATIC);
        foreach ($reflectionProperties as $reflectionProperty) {
            $properties[$reflectionProperty->getName()] = $reflectionProperty->getValue();
        }

        return $properties;
    }

    /**
     * Gets static property value
     *
     * @inheritDoc
     */
    public function getStaticPropertyValue(string $name, mixed $default = null): mixed
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
     * @param mixed $args Accepts a variable number of arguments which are passed to the class constructor
     */
    public function newInstance(...$args): object
    {
        $this->initializeInternalReflection();

        return parent::newInstance(...$args);
    }

    /**
     * Creates a new class instance from given arguments.
     *
     * @link http://php.net/manual/en/reflectionclass.newinstanceargs.php
     *
     * @param array $args The parameters to be passed to the class constructor as an array.
     */
    public function newInstanceArgs(array $args = []): ?object
    {
        $this->initializeInternalReflection();

        return parent::newInstanceArgs($args);
    }

    /**
     * Creates a new class instance without invoking the constructor.
     *
     * @link http://php.net/manual/en/reflectionclass.newinstancewithoutconstructor.php
     */
    public function newInstanceWithoutConstructor(): object
    {
        $this->initializeInternalReflection();

        return parent::newInstanceWithoutConstructor();
    }

    /**
     * Sets static property value
     *
     * @link http://php.net/manual/en/reflectionclass.setstaticpropertyvalue.php
     *
     * @param string $name  Property name
     * @param mixed  $value New property value
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
     * @return \ReflectionClass The appropriate reflection object.
     */
    abstract protected function createReflectionForClass(string $className): \ReflectionClass;
}
