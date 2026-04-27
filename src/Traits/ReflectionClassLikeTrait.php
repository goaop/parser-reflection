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
use Go\ParserReflection\ReflectionEngine;
use Go\ParserReflection\ReflectionException;
use Go\ParserReflection\ReflectionMethod;
use Go\ParserReflection\ReflectionProperty;
use Go\ParserReflection\Resolver\NodeExpressionResolver;
use PhpParser\Modifiers;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassConst;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
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
     *
     * @var non-empty-string
     */
    protected string $className;

    /**
     * List of all constants from the class or null if not initialized yet
     *
     * @var array<string, mixed>|null
     */
    protected ?array $constants;

    /**
     * Interfaces or null if not initialized yet
     *
     * @var \ReflectionClass<object>[]|null
     */
    protected ?array $interfaceClasses;

    /**
     * List of traits or null if not initialized yet
     *
     * @var \ReflectionClass<object>[]|null
     */
    protected ?array $traits;

    /**
     * Additional list of trait adaptations
     *
     * @var TraitUseAdaptation[]
     */
    protected array $traitAdaptations = [];

    /**
     * @var array<string, \ReflectionMethod>|null
     */
    protected ?array $methods;

    /**
     * Namespace name
     */
    protected string $namespaceName = '';

    /**
     * Parent class, or false if not present, null if uninitialized yet
     *
     * @var \ReflectionClass<object>|false|null
     */
    protected null|\ReflectionClass|false $parentClass;

    /**
     * @var array<string, \ReflectionProperty>|null
     */
    protected ?array $properties;

    /**
     * @var array<string, \ReflectionClassConstant>|null
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

        $buildString = static function (array $items, int $indentLevel = 4): string {
            if (!count($items)) {
                return '';
            }
            $indent  = "\n" . str_repeat(' ', $indentLevel);
            $joined  = implode("\n", array_map('strval', array_filter($items, 'is_scalar')))
                . implode("\n", array_map(static fn(\Stringable $item): string => (string) $item, array_filter($items, fn($item): bool => $item instanceof \Stringable)));

            return $indent . implode($indent, explode("\n", $joined));
        };

        $buildConstants = static function (array $items, int $indentLevel = 4): string {
            $str = '';
            foreach ($items as $name => $value) {
                $str .= "\n" . str_repeat(' ', $indentLevel);
                $str .= sprintf(
                    'Constant [ %s %s ] { %s }',
                    gettype($value),
                    $name,
                    is_scalar($value) || $value === null ? $value : ''
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
            return $this->getConstants()[$name];
        }

        return false;
    }

    /**
     * {@inheritDoc}
     *
     * @return array<string, mixed>
     */
    public function getConstants(?int $filter = null): array
    {
        if (!isset($this->constants)) {
            $this->constants = $this->collectInheritedConstants();
            $this->collectSelfConstants();
        }

        return $this->constants ?? [];
    }

    /**
     * Collects constants from parent classes, traits, and interfaces.
     *
     * @return array<string, mixed>
     */
    private function collectInheritedConstants(): array
    {
        $result = [];
        foreach ($this->getTraits() as $trait) {
            $result += $trait->getConstants();
        }
        $parentClass = $this->getParentClass();
        if ($parentClass !== false) {
            $result += $parentClass->getConstants();
        }
        foreach (ReflectionClass::collectInterfacesFromClassNode($this->classLikeNode) as $interface) {
            $result += $interface->getConstants();
        }

        return $result;
    }

    /**
     * {@inheritDoc}
     */
    public function getConstructor(): ?\ReflectionMethod
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
     * @return array<string, mixed> An array of default properties, with the key being the name of the property and the value being
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
                    $declaringClass  = $property->getDeclaringClass();
                    $classProperties = $declaringClass->getDefaultProperties();

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
        $endLine = $this->classLikeNode->getAttribute('endLine');

        return is_int($endLine) ? $endLine : false;
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
        $fileName = $this->classLikeNode->getAttribute('fileName');

        return is_string($fileName) ? $fileName : false;
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
     *
     * @return \ReflectionClass<object>[]
     */
    public function getInterfaces(): array
    {
        if (!isset($this->interfaceClasses)) {
            $this->interfaceClasses = $this->recursiveCollect(
                function (\ReflectionClass $instance, bool $isParent): array {
                    $result = [];
                    if ($instance->isInterface()) {
                        $result[$instance->name] = $instance;
                    }

                    return $result + $instance->getInterfaces();
                }
            );
        }

        return $this->interfaceClasses;
    }

    /**
     * {@inheritdoc}
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
     * @return \ReflectionMethod[]
     */
    public function getMethods(int|null $filter = null): array
    {
        if (!isset($this->methods)) {
            $directMethods = ReflectionMethod::collectFromClassNode($this->classLikeNode, $this);
            $traitMethods  = $this->collectTraitMethods();

            // Collect from parent class and interfaces only (traits are handled by collectTraitMethods)
            $inheritedMethods = [];
            $parentClass = $this->getParentClass();
            if ($parentClass) {
                foreach ($parentClass->getMethods() as $reflectionMethod) {
                    if (!$reflectionMethod->isPrivate()) {
                        $inheritedMethods[$reflectionMethod->name] = $reflectionMethod;
                    }
                }
            }
            $interfaces = ReflectionClass::collectInterfacesFromClassNode($this->classLikeNode);
            foreach ($interfaces as $interface) {
                foreach ($interface->getMethods() as $reflectionMethod) {
                    $inheritedMethods[$reflectionMethod->name] = $reflectionMethod;
                }
            }

            $methods = $directMethods + $traitMethods + $inheritedMethods;

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
     *
     * @return class-string<object>
     */
    public function getName(): string
    {
        $namespaceName = $this->namespaceName ? $this->namespaceName . '\\' : '';
        $fullName = $namespaceName . $this->getShortName();

        return $this->resolveAsClassString($fullName);
    }

    /**
     * Returns a fully-qualified class name. The name is semantically a class-string (it comes
     * from the AST of a PHP class declaration), but PHPStan cannot verify this without
     * autoloading, which would violate the library's contract of reflecting without loading.
     *
     * @param non-empty-string $name
     * @return class-string<object>
     */
    private function resolveAsClassString(string $name): string
    {
        return $name;
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
     *
     * @return \ReflectionClass<object>|false
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
     * @return \ReflectionProperty[]
     */
    public function getProperties(int|null $filter = null): array
    {
        if (!isset($this->properties)) {
            $directProperties = ReflectionProperty::collectFromClassNode($this->classLikeNode, $this->getName());
            $parentProperties = $this->recursiveCollect(
                function (\ReflectionClass $instance, bool $isParent): array {
                    $reflectionProperties = [];
                    foreach ($instance->getProperties() as $reflectionProperty) {
                        if (!$isParent || !$reflectionProperty->isPrivate()) {
                            $reflectionProperties[$reflectionProperty->name] = $reflectionProperty;
                        }
                    }

                    return $reflectionProperties;
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
                function (\ReflectionClass $instance, bool $isParent): array {
                    $reflectionClassConstants = [];
                    foreach ($instance->getReflectionConstants() as $reflectionClassConstant) {
                        if (!$isParent || !$reflectionClassConstant->isPrivate()) {
                            $reflectionClassConstants[$reflectionClassConstant->name] = $reflectionClassConstant;
                        }
                    }

                    return $reflectionClassConstants;
                }
            );
            $classConstants = $directClassConstants + $parentClassConstants;

            $this->classConstants = $classConstants;
        }

        return array_values($this->classConstants);
    }

    /**
     * {@inheritDoc}
     *
     * @return non-empty-string
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

            return is_int($lastAttrGroupsEndLine) ? $lastAttrGroupsEndLine + 1 : false;
        }

        $startLine = $this->classLikeNode->getAttribute('startLine');

        return is_int($startLine) ? $startLine : false;
    }

    /**
     * Returns an array of trait aliases
     *
     * @link http://php.net/manual/en/reflectionclass.gettraitaliases.php
     *
     * @return array<string, string> an array with new method names in keys and original names (in the format
     *                    "TraitName::original") in values.
     */
    public function getTraitAliases(): array
    {
        $aliases = [];
        $traits  = $this->getTraits();
        foreach ($this->traitAdaptations as $adaptation) {
            if ($adaptation instanceof TraitUseAdaptation\Alias) {
                $methodName = (string) $adaptation->method;
                $traitName  = null;
                foreach ($traits as $trait) {
                    if ($trait->hasMethod($methodName)) {
                        $traitName = $trait->getName();
                        break;
                    }
                }
                $aliases[(string) $adaptation->newName] = $traitName . '::' . $methodName;
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
     * @return \ReflectionClass<object>[]
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
     *
     * @param \ReflectionClass<object>|string $interfaceName
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

        if ($this->isInterface() && (!empty($this->getMethods()) || !empty($this->getProperties()))) {
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
     *
     * @param \ReflectionClass<object>|string $class
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
        if ($extends instanceof Name && $extends->getAttribute('resolvedName') instanceof FullyQualified) {
            if ($extends->getAttribute('resolvedName')->toString() === $className) {
                return true;
            }
        }

        foreach ($this->classLikeNode->implements as $implementedInterface) {
            if ($implementedInterface->getAttribute('resolvedName') instanceof FullyQualified) {
                if ($implementedInterface->getAttribute('resolvedName')->toString() === $className) {
                    return true;
                }
            }
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
     *
     * @return array<string, mixed>
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
     * @param array<int, mixed> $args The parameters to be passed to the class constructor as an array.
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

    /**
     * @template TValue
     * @param \Closure(\ReflectionClass<object>, bool): array<string, TValue> $collector
     * @return array<string, TValue>
     */
    private function recursiveCollect(Closure $collector): array
    {
        $result   = [];
        $isParent = true;

        $traits = $this->getTraits();
        foreach ($traits as $trait) {
            $result += $collector($trait, false);
        }

        $parentClass = $this->getParentClass();
        if ($parentClass) {
            $result += $collector($parentClass, $isParent);
        }

        $interfaces = ReflectionClass::collectInterfacesFromClassNode($this->classLikeNode);
        foreach ($interfaces as $interface) {
            $result += $collector($interface, $isParent);
        }

        return $result;
    }

    /**
     * Collects methods from all used traits, applying insteadof and alias adaptations.
     *
     * @return array<string, \ReflectionMethod>
     */
    private function collectTraitMethods(): array
    {
        $this->getTraits(); // Ensure traits and traitAdaptations are initialized
        $traits = $this->traits ?? [];

        if (empty($traits)) {
            return [];
        }

        // The class that uses the traits — used as $className in ReflectionMethod so that the
        // `class` property (and __debugInfo) match native PHP behaviour.
        $usingClassName = $this->getName();

        // Parse each trait's AST and build a map of ClassMethod nodes per trait.
        // Also keep a ReflectionClass for the trait (used as $declaringClass).
        /** @var array<string, array<string, ClassMethod>> $traitClassMethodNodes */
        $traitClassMethodNodes = [];
        /** @var array<string, ReflectionClass> $traitReflections */
        $traitReflections = [];

        foreach ($traits as $traitName => $existingReflection) {
            $traitClassNode = ReflectionEngine::parseClass($traitName);
            // Reuse the existing ReflectionClass if it's our AST-based implementation;
            // otherwise (when the trait was already loaded and a native instance was stored)
            // create a new AST-based ReflectionClass for use as $declaringClass.
            $traitReflections[$traitName] = $existingReflection instanceof ReflectionClass
                ? $existingReflection
                : new ReflectionClass($traitName, $traitClassNode);
            $methodNodes = [];
            foreach ($traitClassNode->stmts as $stmt) {
                if ($stmt instanceof ClassMethod) {
                    // Mirror what collectFromClassNode does: propagate the file name
                    $stmt->setAttribute('fileName', $traitClassNode->getAttribute('fileName'));
                    $methodNodes[$stmt->name->toString()] = $stmt;
                }
            }
            $traitClassMethodNodes[$traitName] = $methodNodes;
        }

        // Build exclusion map from Precedence (insteadof) adaptations:
        // $excluded[traitFQN][methodName] = true means that method from that trait is excluded
        $excluded = [];
        foreach ($this->traitAdaptations as $adaptation) {
            if ($adaptation instanceof TraitUseAdaptation\Precedence) {
                $methodName = $adaptation->method->toString();
                foreach ($adaptation->insteadof as $excludedTraitNameNode) {
                    $resolvedName   = $excludedTraitNameNode->getAttribute('resolvedName');
                    $excludedFQN    = $resolvedName instanceof FullyQualified
                        ? $resolvedName->toString()
                        : $excludedTraitNameNode->toString();
                    $excluded[$excludedFQN][$methodName] = true;
                }
            }
        }

        // Collect trait methods respecting insteadof: first non-excluded method wins
        $traitMethods = [];
        foreach ($traitClassMethodNodes as $traitName => $methodNodes) {
            foreach ($methodNodes as $methodName => $methodNode) {
                if (isset($excluded[$traitName][$methodName])) {
                    continue; // Excluded by insteadof
                }
                if (isset($traitMethods[$methodName])) {
                    continue; // Already added from an earlier trait
                }
                $traitMethods[$methodName] = new ReflectionMethod(
                    $usingClassName,
                    $methodName,
                    $methodNode,
                    $traitReflections[$traitName]
                );
            }
        }

        // Apply Alias adaptations: add methods with new names and/or changed visibility
        foreach ($this->traitAdaptations as $adaptation) {
            if (!($adaptation instanceof TraitUseAdaptation\Alias)) {
                continue;
            }

            $originalMethodName = $adaptation->method->toString();
            $newName            = $adaptation->newName !== null ? $adaptation->newName->toString() : null;
            $newModifier        = $adaptation->newModifier;

            // Find the ClassMethod node for the original method
            $originalMethodNode = null;
            $declaringTraitName = null;

            if ($adaptation->trait !== null) {
                // Specific trait referenced — resolve to FQCN
                $resolvedName = $adaptation->trait->getAttribute('resolvedName');
                $traitFQN     = $resolvedName instanceof FullyQualified
                    ? $resolvedName->toString()
                    : $adaptation->trait->toString();

                if (isset($traitClassMethodNodes[$traitFQN][$originalMethodName])) {
                    $originalMethodNode = $traitClassMethodNodes[$traitFQN][$originalMethodName];
                    $declaringTraitName = $traitFQN;
                }
            } else {
                // No specific trait — search all traits in declaration order
                foreach ($traitClassMethodNodes as $traitFQN => $methodNodes) {
                    if (isset($methodNodes[$originalMethodName])) {
                        $originalMethodNode = $methodNodes[$originalMethodName];
                        $declaringTraitName = $traitFQN;
                        break;
                    }
                }
            }

            if ($originalMethodNode === null || $declaringTraitName === null) {
                continue;
            }

            // Clone the AST node and apply name/visibility changes
            $aliasMethodNode  = clone $originalMethodNode;
            $targetMethodName = $newName ?? $originalMethodName;

            if ($newName !== null) {
                $aliasMethodNode->name = new Identifier($newName);
            }
            if ($newModifier !== null) {
                // Clear existing visibility bits and apply the new modifier
                $aliasMethodNode->flags =
                    ($aliasMethodNode->flags & ~Modifiers::VISIBILITY_MASK) | $newModifier;
            }

            $traitMethods[$targetMethodName] = new ReflectionMethod(
                $usingClassName,
                $targetMethodName,
                $aliasMethodNode,
                $traitReflections[$declaringTraitName]
            );
        }

        return $traitMethods;
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

                        $this->constants = $localConstants + ($this->constants ?? []);
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
