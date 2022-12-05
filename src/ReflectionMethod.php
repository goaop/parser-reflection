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

use Closure;
use Error;
use Go\ParserReflection\Traits\InternalPropertiesEmulationTrait;
use Go\ParserReflection\Traits\ReflectionFunctionLikeTrait;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use Reflection;
use ReflectionException as BaseReflectionException;
use ReflectionMethod as BaseReflectionMethod;

/**
 * AST-based reflection for the method in a class
 */
class ReflectionMethod extends BaseReflectionMethod
{
    use InternalPropertiesEmulationTrait;
    use ReflectionFunctionLikeTrait;

    /**
     * Name of the alias for the method
     *
     * @var string|null
     */
    private ?string $aliasName = null;

    /**
     * Reference to the alias class
     *
     * @var ReflectionClass|null
     */
    private ?ReflectionClass $aliasClass = null;

    /**
     * Initializes reflection instance for the method node
     *
     * @param string           $className       Name of the class
     * @param string           $methodName      Name of the method
     * @param ?ClassMethod     $classMethodNode AST-node for method
     * @param ?ReflectionClass $declaringClass  Optional declaring class
     *
     * @throws ReflectionException
     *
     * @noinspection PhpMissingParentConstructorInspection
     */
    public function __construct(
        private string $className,
        string $methodName,
        ClassMethod $classMethodNode = null,
        private ?ReflectionClass $declaringClass = null
    ) {
        // For some reason, ReflectionMethod->getNamespaceName in php always returns '', so we shouldn't use it too
        $this->className        = ltrim($className, '\\');
        $this->functionLikeNode = $classMethodNode ?: ReflectionEngine::parseClassMethod($className, $methodName);

        // Let's unset original read-only properties to have a control over them via __get
        unset($this->name, $this->class);
    }

    /**
     * Returns an AST-node for method
     *
     * @return ClassMethod
     */
    public function getNode(): ClassMethod
    {
        return $this->functionLikeNode;
    }

    /**
     * Emulating original behaviour of reflection.
     *
     * Called when invoking {@link var_dump()} on an object
     *
     * @return array{name: string, class: class-string}
     */
    public function __debugInfo(): array
    {
        try {
            $name  = $this->aliasName  ?? $this->getClassMethodNode()->name->toString();
            $class = $this->aliasClass ? $this->aliasClass->getName() : $this->className;
        } catch (Error) {
            // If we are here, then we are in the middle of the object creation
            $name  = null;
            $class = null;
        }

        return [
            'name'  => $name,
            'class' => $class,
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
        // Internally $this->getReturnType() !== null is the same as $this->hasReturnType()
        $returnType       = $this->getReturnType();
        $hasReturnType    = $returnType !== null;
        $paramsNeeded     = $hasReturnType || $this->getNumberOfParameters() > 0;
        $paramFormat      = $paramsNeeded ? "\n\n  - Parameters [%d] {%s\n  }" : '';
        $returnFormat     = $hasReturnType ? "\n  - Return [ %s ]" : '';
        $methodParameters = $this->getParameters();
        try {
            $prototype = $this->getPrototype();
        } catch (BaseReflectionException) {
            $prototype = null;
        }
        $prototypeClass = $prototype ? $prototype->getDeclaringClass()->name : '';

        $paramString = '';
        $indentation = str_repeat(' ', 4);
        foreach ($methodParameters as $methodParameter) {
            $paramString .= "\n$indentation" . $methodParameter;
        }

        /** @noinspection PhpFormatFunctionParametersMismatchInspection */
        return sprintf(
            "%sMethod [ <user%s%s%s>%s%s%s %s method %s ] {\n  @@ %s %d - %d$paramFormat$returnFormat\n}\n",
            $this->getDocComment() ? $this->getDocComment() . "\n" : '',
            $prototype ? ", overwrites $prototypeClass, prototype $prototypeClass" : '',
            $this->isConstructor() ? ', ctor' : '',
            $this->isFinal() ? ' final' : '',
            $this->isStatic() ? ' static' : '',
            $this->isAbstract() ? ' abstract' : '',
            implode(
                ' ',
                Reflection::getModifierNames(
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
     * @param string $name
     * @param array  $arguments
     *
     * @return void
     */
    public function __call(string $name, array $arguments): void
    {
        if ($name === 'setAliasName') {
            $this->setAliasName(...$arguments);
        } elseif ($name === 'setAliasClass') {
            $this->setAliasClass(...$arguments);
        } elseif ($name === 'setModifiers') {
            $this->setModifiers(...$arguments);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getClosure($object = null): ?Closure
    {
        $this->initializeInternalReflection();

        return parent::getClosure($object);
    }

    /**
     * Gets declaring class for the reflected method.
     *
     * @link https://php.net/manual/en/reflectionmethod.getdeclaringclass.php
     *
     * @return ReflectionClass A {@see ReflectionClass} object of the class that the
     *                         reflected method is part of.
     *
     * @noinspection PhpDocMissingThrowsInspection
     */
    public function getDeclaringClass(): ReflectionClass
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        return $this->declaringClass ?? new ReflectionClass($this->className);
    }

    /**
     * {@inheritDoc}
     */
    public function getModifiers(): int
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
    public function getPrototype(): BaseReflectionMethod|ReflectionMethod
    {
        $parent = $this->getDeclaringClass()->getParentClass();
        if (!$parent) {
            throw new ReflectionException("No prototype");
        }

        try {
            $prototypeMethod = $parent->getMethod($this->getName());
        } catch (ReflectionException) {
            throw new ReflectionException("No prototype");
        }

        return $prototypeMethod;
    }

    /**
     * {@inheritDoc}
     */
    public function invoke($object, ...$args)
    {
        $this->initializeInternalReflection();

        return parent::invoke($object, ...$args);
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
    public function isAbstract(): bool
    {
        return $this->getDeclaringClass()->isInterface() || $this->getClassMethodNode()->isAbstract();
    }

    /**
     * {@inheritDoc}
     */
    public function isConstructor(): bool
    {
        return $this->getClassMethodNode()->name->toLowerString() === '__construct';
    }

    /**
     * {@inheritDoc}
     */
    public function isDestructor(): bool
    {
        return $this->getClassMethodNode()->name->toLowerString() === '__destruct';
    }

    /**
     * {@inheritDoc}
     */
    public function isFinal(): bool
    {
        return $this->getClassMethodNode()->isFinal();
    }

    /**
     * {@inheritDoc}
     */
    public function isPrivate(): bool
    {
        return $this->getClassMethodNode()->isPrivate();
    }

    /**
     * {@inheritDoc}
     */
    public function isProtected(): bool
    {
        return $this->getClassMethodNode()->isProtected();
    }

    /**
     * {@inheritDoc}
     */
    public function isPublic(): bool
    {
        return $this->getClassMethodNode()->isPublic();
    }

    /**
     * {@inheritDoc}
     */
    public function isStatic(): bool
    {
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
     * @return ReflectionMethod[]
     */
    public static function collectFromClassNode(ClassLike $classLikeNode, ReflectionClass $reflectionClass): array
    {
        $methods = [];

        foreach ($classLikeNode->stmts as $classLevelNode) {
            if ($classLevelNode instanceof ClassMethod) {
                $classLevelNode->setAttribute('fileName', $classLikeNode->getAttribute('fileName'));

                $methodName = $classLevelNode->name->toString();

                try {
                    $methods[$methodName] = new ReflectionMethod(
                        $reflectionClass->name,
                        $methodName,
                        $classLevelNode,
                        $reflectionClass
                    );
                } catch (ReflectionException) {
                    // Ignore methods that cannot be parsed
                }
            }
        }

        return $methods;
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
        parent::__construct($this->className, $this->getName());
    }

    /**
     * Sets the alias for the method
     *
     * @param string $aliasName
     *
     * @return void
     */
    protected function setAliasName(string $aliasName): void
    {
        $this->aliasName = $aliasName;
    }

    /**
     * Sets the alias class
     *
     * @param ReflectionClass $aliasClass
     *
     * @return void
     */
    protected function setAliasClass(ReflectionClass $aliasClass): void
    {
        $this->aliasClass = $aliasClass;
    }

    /**
     * Set new modifiers
     *
     * @param int $modifiers
     *
     * @return void
     */
    protected function setModifiers(int $modifiers): void
    {
        $this->getClassMethodNode()->flags = $modifiers;
    }

    /**
     * Returns ClassMethod node to prevent all possible type checks with instanceof
     */
    private function getClassMethodNode(): ClassMethod
    {
        return $this->functionLikeNode;
    }
}
