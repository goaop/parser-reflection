<?php
/** @noinspection PhpElementIsNotAvailableInCurrentPhpVersionInspection */
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
use ReflectionAttribute as BaseReflectionAttribute;
use ReflectionClass as BaseReflectionClass;
use ReflectionClassConstant as BaseReflectionClassConstant;
use ReflectionException as BaseReflectionException;
use ReflectionExtension as BaseReflectionExtension;
use ReflectionMethod as BaseReflectionMethod;
use ReflectionObject as BaseReflectionObject;
use ReflectionProperty as BaseReflectionProperty;
use ReturnTypeWillChange;
use Traversable;

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
    public ?array $constants = null;

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
     * @var TraitUseAdaptation[]
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
     * Returns the string representation of the ReflectionClass object.
     *
     * @link https://php.net/manual/en/reflectionclass.tostring.php
     *
     * @return string A string representation of this {@see BaseReflectionClass} instance.
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
     * Gets defined constant
     *
     * @link https://php.net/manual/en/reflectionclass.getconstant.php
     *
     * @param string $name Name of the constant.
     *
     * @return mixed|false Value of the constant with the name.
     *                     Returns {@see false} if the constant was not found in the class.
     */
    public function getConstant(string $name): mixed
    {
        if ($this->hasConstant($name)) {
            return $this->constants[$name];
        }

        return false;
    }

    /**
     * Gets constants
     *
     * @link https://php.net/manual/en/reflectionclass.getconstants.php
     *
     * @param int|null $filter [optional] allows the filtering of constants defined in a class by their visibility.
     *
     * @return array An array of constants, where the keys hold the name and
     *               the values the value of the constants.
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
     * Gets the constructor of the class
     *
     * @link https://php.net/manual/en/reflectionclass.getconstructor.php
     *
     * @return BaseReflectionMethod|null A {@see BaseReflectionMethod} object reflecting the class' constructor,
     *                                   or {@see null} if the class has no constructor.
     */
    public function getConstructor(): ?BaseReflectionMethod
    {
        try {
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
     *               the default value of the property or NULL if the property doesn't have a default value
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

                if (!$property->isPromoted()) {
                    if ((!$isInternalReflection || $isStaticProperty)) {
                        $defaultValues[$propertyName] = $property->getValue();
                    } else {
                        // Internal reflection and dynamic property
                        $classProperties = $property->getDeclaringClass()
                            ->getDefaultProperties();

                        $defaultValues[$propertyName] = $classProperties[$propertyName];
                    }
                }
            }
        }

        return $defaultValues;
    }

    /**
     * Gets doc comments
     *
     * @link https://php.net/manual/en/reflectionclass.getdoccomment.php
     *
     * @return string|false The doc comment if it exists, otherwise {@see false}
     */
    public function getDocComment(): string|false
    {
        $docComment = $this->classLikeNode->getDocComment();

        return $docComment ? $docComment->getText() : false;
    }

    /**
     * Gets end line
     *
     * @link https://php.net/manual/en/reflectionclass.getendline.php
     *
     * @return int|false The ending line number of the user defined class, or
     *                   {@see false} if unknown.
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
     * Gets the name of the extension which defined the class
     *
     * @link https://php.net/manual/en/reflectionclass.getextensionname.php
     *
     * @return string|false The name of the extension which defined the class,
     *                      or {@see false} for user-defined classes.
     */
    public function getExtensionName(): string|false
    {
        return false;
    }

    /**
     * Gets the filename of the file in which the class has been defined
     *
     * @link https://php.net/manual/en/reflectionclass.getfilename.php
     *
     * @return string|false the filename of the file in which the class has been defined.
     *                      If the class is defined in the PHP core or in a PHP extension, {@see false}
     *                      is returned.
     */
    public function getFileName(): string|false
    {
        return $this->classLikeNode->getAttribute('fileName');
    }

    /**
     * Gets the interface names
     *
     * @link https://php.net/manual/en/reflectionclass.getinterfacenames.php
     *
     * @return string[] A numerical array with interface names as the values.
     */
    public function getInterfaceNames(): array
    {
        return array_keys($this->getInterfaces());
    }

    /**
     * Gets the interfaces
     *
     * @link https://php.net/manual/en/reflectionclass.getinterfaces.php
     *
     * @return BaseReflectionClass[] An associative array of interfaces, with keys as interface
     *                               names and the array values as {@see BaseReflectionClass} objects.
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
    public function getMethod(string $name): ReflectionMethod
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
     * Gets an array of methods for the class.
     *
     * @link https://php.net/manual/en/reflectionclass.getmethods.php
     *
     * @param int|null $filter Filter the results to include only methods
     *                         with certain attributes. Defaults to no filtering.
     *
     * @return ReflectionMethod[] An array of {@see ReflectionMethod} objects
     *                                reflecting each method.
     */
    public function getMethods(?int $filter = null): array
    {
        if (!isset($this->methods)) {
            $directMethods = ReflectionMethod::collectFromClassNode($this->classLikeNode, $this);
            $parentMethods = $this->recursiveCollect(
                function (array &$result, ReflectionClass $instance, $isParent) {
                    $reflectionMethods = [];
                    foreach ($instance->getMethods() as $reflectionMethod) {
                        if (!$isParent || !$reflectionMethod->isPrivate()) {
                            $reflectionMethodName = $reflectionMethod->getName();

                            if ($instance->isTrait()) {
                                $insteadOfSkipped = [];

                                // Collect by trait aliases
                                foreach ($this->getTraitAliases() as $newMethodName => $traitAlias) {
                                    $traitAliasParts = explode('::', $traitAlias);
                                    $traitName = $traitAliasParts[0];
                                    $traitMethodName = $traitAliasParts[1];
                                    if ($traitName === $instance->getName()
                                        && $traitMethodName === $reflectionMethodName
                                    ) {
                                        foreach ($this->traitAdaptations as $adaptation) {
                                            if ($adaptation->method->toString() === $reflectionMethod->getName()) {
                                                // Alias
                                                if (isset($adaptation->newName)
                                                    && $adaptation->newName->toString() === $newMethodName
                                                ) {
                                                    $reflectionMethodAlias = new ReflectionMethod(
                                                        $this->getName(),
                                                        $reflectionMethodName,
                                                        clone $reflectionMethod->getNode(),
                                                        $this
                                                    );
                                                    $reflectionMethodAlias->setAliasName($newMethodName);
                                                    $reflectionMethodAlias->setAliasClass($this);
                                                    if (!is_null($adaptation->newModifier)) {
                                                        $reflectionMethodAlias->setModifiers($adaptation->newModifier);
                                                    }
                                                    $reflectionMethods[$newMethodName] = $reflectionMethodAlias;
                                                }

                                                // Override
                                                elseif (isset($adaptation->insteadof)) {
                                                    foreach ($adaptation->insteadof as $insteadOf) {
                                                        $insteadOfSkipped[] = $insteadOf->toString();
                                                    }

                                                    $reflectionMethodInsteadOf = new ReflectionMethod(
                                                        $adaptation->trait->toString(),
                                                        $reflectionMethodName,
                                                        null,
                                                        $this
                                                    );

                                                    $reflectionMethods[$reflectionMethodName] = $reflectionMethodInsteadOf;
                                                }
                                            }
                                        }
                                    }
                                }

                                // Collect base traits
                                foreach ($this->getTraitNames() as $traitName) {
                                    if ($traitName === $instance->getName()
                                        && !in_array($traitName, $insteadOfSkipped, true)
                                    ) {
                                        $reflectionMethodAlias = new ReflectionMethod(
                                            $this->getName(),
                                            $reflectionMethodName,
                                            $reflectionMethod->getNode(),
                                            $this
                                        );
                                        $reflectionMethods[$reflectionMethodName] = $reflectionMethodAlias;
                                    }
                                }
                            } else {
                                $reflectionMethods[$reflectionMethodName] = $reflectionMethod;
                            }
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
     * Gets modifiers
     *
     * @link https://php.net/manual/en/reflectionclass.getmodifiers.php
     *
     * @return int bitmask of modifier constants.
     */
    public function getModifiers(): int
    {
        /** @see https://github.com/php/php-src/blob/PHP-8.0.25/ext/reflection/php_reflection.c#L4674-L4687 */
        $modifiers = 0;

        if ($this->isFinal()) {
            $modifiers += BaseReflectionClass::IS_FINAL;
        }

        if ($this->classLikeNode instanceof Class_ && $this->classLikeNode->isAbstract()) {
            $modifiers += BaseReflectionClass::IS_EXPLICIT_ABSTRACT;
        }

        return $modifiers;
    }

    /**
     * Gets class name
     *
     * @link https://php.net/manual/en/reflectionclass.getname.php
     *
     * @return string The class name.
     */
    public function getName(): string
    {
        $namespaceName = $this->namespaceName ? $this->namespaceName . '\\' : '';

        return $namespaceName . $this->getShortName();
    }

    /**
     * Gets namespace name
     *
     * @link https://php.net/manual/en/reflectionclass.getnamespacename.php
     *
     * @return string The namespace name.
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
     *                                   if there's no parent.
     */
    public function getParentClass(): ReflectionClass|false
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
     * Gets properties
     *
     * @link https://php.net/manual/en/reflectionclass.getproperties.php
     *
     * @param int|null $filter The optional filter, for filtering desired property types. It's configured using
     *                         the {@see BaseReflectionProperty} constants, and defaults to all property types.
     *
     * @return BaseReflectionProperty[]
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
            $properties = $directProperties + $parentProperties;

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
     * @return BaseReflectionProperty A {@see BaseReflectionProperty}
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
     * @return BaseReflectionClassConstant|false A {@see BaseReflectionClassConstant}.
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
     * Gets class constants
     *
     * @link https://php.net/manual/en/reflectionclass.getreflectionconstants.php
     *
     * @param int|null $filter Allows the filtering of constants defined in a class by their visibility.
     *
     * @return BaseReflectionClassConstant[] An array of ReflectionClassConstant objects.
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
            $classConstants = $directClassConstants + $parentClassConstants;

            $this->classConstants = $classConstants;
        }

        return array_values($this->classConstants);
    }

    /**
     * Gets short name
     *
     * @link https://php.net/manual/en/reflectionclass.getshortname.php
     *
     * @return string The class short name.
     */
    public function getShortName(): string
    {
        return $this->className;
    }

    /**
     * Gets starting line number
     *
     * @link https://php.net/manual/en/reflectionclass.getstartline.php
     *
     * @return int|false The starting line number, as an integer.
     */
    public function getStartLine(): int|false
    {
        return $this->classLikeNode->getAttribute('startLine');
    }

    /**
     * Returns an array of trait aliases
     *
     * @link https://php.net/manual/en/reflectionclass.gettraitaliases.php
     *
     * @return string[] An array with new method names in keys and original
     *                  names (in the format "TraitName::original") in values.
     *                  Returns {@see null} in case of an error.
     */
    public function getTraitAliases(): array
    {
        $aliases = [];
        $traits  = $this->getTraits();
        foreach ($this->traitAdaptations as $adaptation) {
            if ($adaptation instanceof TraitUseAdaptation\Alias) {
                $methodName = $adaptation->method->toString();
                $traitName  = null;
                foreach ($traits as $trait) {
                    if ($trait->hasMethod($methodName)) {
                        $traitName = $trait->getName();
                        break;
                    }
                }
                $aliases[$adaptation->newName->toString()] = $traitName . '::' . $methodName;
            }
        }

        return $aliases;
    }

    /**
     * Returns an array of names of traits used by this class
     *
     * @link https://php.net/manual/en/reflectionclass.gettraitnames.php
     *
     * @return string[] An array with trait names in values.
     *                  Returns {@see null} in case of an error.
     */
    public function getTraitNames(): array
    {
        return array_keys($this->getTraits());
    }

    /**
     * Returns an array of traits used by this class
     *
     * @link https://php.net/manual/en/reflectionclass.gettraits.php
     *
     * @return ReflectionClass[] An array with trait names in keys and
     *                               instances of trait's {@see ReflectionClass} in values.
     */
    public function getTraits(): array
    {
        if (!isset($this->traits)) {
            $traitAdaptations = [];
            $this->traits     = ReflectionClass::collectTraitsFromClassNode(
                $this->classLikeNode,
                $traitAdaptations
            );
            $this->traitAdaptations = $traitAdaptations;
        }

        return $this->traits;
    }

    /**
     * Checks if constant is defined
     *
     * @link https://php.net/manual/en/reflectionclass.hasconstant.php
     *
     * @param string $name The name of the constant being checked for.
     *
     * @return bool Returns {@see true} if the constant is defined, otherwise {@see false}
     */
    public function hasConstant(string $name): bool
    {
        $constants   = $this->getConstants();
        $hasConstant = isset($constants[$name]) || array_key_exists($name, $constants);

        return $hasConstant;
    }

    /**
     * Checks if method is defined
     *
     * @link https://php.net/manual/en/reflectionclass.hasmethod.php
     *
     * @param string $name Name of the method being checked for.
     *
     * @return bool Returns {@see true} if it has the method, otherwise {@see false}
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
     * Checks if property is defined
     *
     * @link https://php.net/manual/en/reflectionclass.hasproperty.php
     *
     * @param string $name Name of the property being checked for.
     *
     * @return bool Returns {@see true} if it has the property, otherwise {@see false}
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
     * @param BaseReflectionClass|string $interface The interface name.
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
     * Checks if in namespace
     *
     * @link https://php.net/manual/en/reflectionclass.innamespace.php
     *
     * @return bool {@see true} on success or {@see false} on failure.
     */
    public function inNamespace(): bool
    {
        return !empty($this->namespaceName);
    }

    /**
     * Checks if class is abstract
     *
     * @link https://php.net/manual/en/reflectionclass.isabstract.php
     *
     * @return bool Returns {@see true} on success or {@see false} on failure.
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
     * Returns whether this class is cloneable
     *
     * @link https://php.net/manual/en/reflectionclass.iscloneable.php
     *
     * @return bool Returns {@see true} if the class is cloneable, {@see false} otherwise.
     */
    public function isCloneable(): bool
    {
        if ($this->isInterface() || $this->isTrait() || $this->isAbstract()) {
            return false;
        }

        if ($this->hasMethod('__clone')) {
            try {
                return $this->getMethod('__clone')
                    ->isPublic();
            } catch (ReflectionException) {
                return false;
            }
        }

        return true;
    }

    /**
     * Checks if class is final
     *
     * @link https://php.net/manual/en/reflectionclass.isfinal.php
     *
     * @return bool Returns {@see true} on success or {@see false} on failure.
     */
    public function isFinal(): bool
    {
        $isFinal = $this->classLikeNode instanceof Class_ && $this->classLikeNode->isFinal();

        return $isFinal;
    }

    /**
     * Checks class for instance
     *
     * @link https://php.net/manual/en/reflectionclass.isinstance.php
     *
     * @param object $object The object being compared to.
     *
     * @return bool Returns {@see true} on success or {@see false} on failure.
     */
    public function isInstance(object $object): bool
    {
        $className = $this->getName();

        return $className === get_class($object) || is_subclass_of($object, $className);
    }

    /**
     * Checks if the class is instantiable
     *
     * @link https://php.net/manual/en/reflectionclass.isinstantiable.php
     *
     * @return bool Returns {@see true} on success or {@see false} on failure.
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
     * Checks if the class is an interface
     *
     * @link https://php.net/manual/en/reflectionclass.isinterface.php
     *
     * @return bool Returns {@see true} on success or {@see false} on failure.
     */
    public function isInterface(): bool
    {
        return ($this->classLikeNode instanceof Interface_);
    }

    /**
     * Checks if class is defined internally by an extension, or the core
     *
     * @link https://php.net/manual/en/reflectionclass.isinternal.php
     *
     * @return bool Returns {@see false} as it can never be an internal method.
     */
    public function isInternal(): bool
    {
        // never can be an internal method
        // @todo why method?
        return false;
    }

    /**
     * An alias of {@see ReflectionClass::isIterable} method.
     *
     * @link https://php.net/manual/en/reflectionclass.isiterateable.php
     *
     * @return bool Returns {@see true} on success or {@see false} on failure.
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
     * @param string|BaseReflectionClass $class Either the name of the class as string or a {@see BaseReflectionClass}
     *                                          object of the class to check against.
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
     * Returns whether this is a trait
     *
     * @link https://php.net/manual/en/reflectionclass.istrait.php
     *
     * @return bool Returns {@see true} if this is a trait, {@see false} otherwise.
     */
    public function isTrait(): bool
    {
        return ($this->classLikeNode instanceof Trait_);
    }

    /**
     * Checks if user defined
     *
     * @link https://php.net/manual/en/reflectionclass.isuserdefined.php
     *
     * @return true Returns {@see true}. Always defined by user, because we are parsing the source code.
     */
    public function isUserDefined(): bool
    {
        return true;
    }

    /**
     * Gets static properties
     *
     * @link https://php.net/manual/en/reflectionclass.getstaticproperties.php
     *
     * @return array|null The static properties, as an array where the keys hold
     *                    the name and the values the value of the properties.
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
    #[ReturnTypeWillChange]
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
     * Sets static property value
     *
     * @link https://php.net/manual/en/reflectionclass.setstaticpropertyvalue.php
     *
     * @param string $name  Property name.
     * @param mixed  $value New property value.
     *
     * @return void No value is returned.
     */
    public function setStaticPropertyValue(string $name, mixed $value): void
    {
        $this->initializeInternalReflection();

        parent::setStaticPropertyValue($name, $value);
    }

    /**
     * Check whether this class is iterable
     *
     * @link https://php.net/manual/en/reflectionclass.isiterable.php
     *
     * @return bool Returns {@see true} on success or {@see false} on failure.
     */
    public function isIterable(): bool
    {
        return $this->implementsInterface(Traversable::class);
    }

    /**
     * Returns an array of class attributes.
     *
     * @template T
     *
     * @param class-string<T>|null $name  Name of an attribute class
     * @param int                  $flags Criteria by which the attribute is searched.
     *
     * @return BaseReflectionAttribute<T>[]
     */
    public function getAttributes(?string $name = null, int $flags = 0): array
    {
        // @todo: implement
        throw new ReflectionException("Not implemented");
    }

    /**
     * Recursively gets all traits, parent classes and interfaces used by this class
     *
     * @param Closure $collector
     *
     * @return array
     */
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
