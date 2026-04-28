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

namespace Go\ParserReflection;

use Go\ParserReflection\Traits\AttributeResolverTrait;
use Go\ParserReflection\Traits\InternalPropertiesEmulationTrait;
use Go\ParserReflection\Traits\ReflectionFunctionLikeTrait;
use PhpParser\Node\Identifier;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Enum_;
use PhpParser\Node\UnionType;
use Reflection;
use ReflectionMethod as BaseReflectionMethod;

/**
 * AST-based reflection for the method in a class
 * @see \Go\ParserReflection\ReflectionMethodTest
 */
final class ReflectionMethod extends BaseReflectionMethod
{
    use InternalPropertiesEmulationTrait;
    use ReflectionFunctionLikeTrait;
    use AttributeResolverTrait;

    /**
     * Name of the class
     */
    private string $className;

    /**
     * Optional declaring class reference
     */
    private ReflectionClass|ReflectionEnum|null $declaringClass;

    /**
     * Optional context class name: when non-null, this method was accessed through a class
     * that differs from $className (the declaring class). Used by __toString() to emit the
     * "inherits ClassName" section for inherited methods.
     */
    private ?string $contextClassName = null;

    /**
     * Initializes reflection instance for the method node
     *
     * @param ?ClassMethod     $classMethodNode AST-node for method
     * @param ReflectionClass|ReflectionEnum|null $declaringClass  Optional declaring class
     */
    public function __construct(
        string $className,
        string $methodName,
        ?ClassMethod $classMethodNode = null,
        ReflectionClass|ReflectionEnum|null $declaringClass = null
    ) {
        //for some reason, ReflectionMethod->getNamespaceName in php always returns '', so we shouldn't use it too
        $this->className        = ltrim($className, '\\');
        $this->declaringClass   = $declaringClass;
        $this->functionLikeNode = $classMethodNode ?: ReflectionEngine::parseClassMethod($className, $methodName);

        // Let's unset original read-only properties to have a control over them via __get
        unset($this->name, $this->class);
    }

    protected function getDeclaringClassNameForTypes(): string
    {
        return $this->getDeclaringClass()->getName();
    }

    protected function getParentClassNameForTypes(): ?string
    {
        $parent = $this->getDeclaringClass()->getParentClass();

        return ($parent !== false) ? $parent->getName() : null;
    }

    /**
     * Returns an AST-node for method
     */
    public function getNode(): ClassMethod
    {
        return $this->getClassMethodNode();
    }

    /**
     * Returns a copy of this method viewed through the context of a different (child) class.
     *
     * When a method is inherited rather than overridden, getMethods() uses this to tag the
     * returned instance with the class it was accessed through. __toString() then emits the
     * ", inherits OriginalClass" section that PHP's own reflection produces.
     */
    public function withContextClass(string $contextClassName): self
    {
        $newMethod = new self(
            $this->className,
            $this->getName(),
            $this->getNode(),
            $this->declaringClass
        );
        $newMethod->contextClassName = $contextClassName;

        return $newMethod;
    }

    /**
     * Returns the AST node that contains attribute groups for this method.
     */
    protected function getNodeForAttributes(): ClassMethod
    {
        return $this->getClassMethodNode();
    }

    /**
     * Emulating original behaviour of reflection
     *
     * @return array<string, string>
     */
    public function __debugInfo(): array
    {
        return [
            'name'  => $this->getClassMethodNode()->name->toString(),
            'class' => $this->className
        ];
    }

    /**
     * Returns the string representation of the Reflection method object.
     *
     * @link http://php.net/manual/en/reflectionmethod.tostring.php
     */
    public function __toString(): string
    {
        // Internally $this->getReturnType() !== null is the same as $this->hasReturnType()
        $returnType       = $this->getReturnType();
        $hasReturnType    = $returnType !== null;
        $paramsNeeded     = $hasReturnType || $this->getNumberOfParameters() > 0;
        $paramFormat      = $paramsNeeded ? "\n\n  - Parameters [%d] {%s\n  }" : '';
        $returnFormat     = $hasReturnType ? "\n  - Return [ %s ]" : '';
        $methodParameters = $this->getParameters();

        $protoString = '';
        if ($this->contextClassName !== null && $this->contextClassName !== $this->className) {
            // This method is inherited: accessed through $contextClassName but declared in $className
            $protoString = ", inherits {$this->className}";
            if ($this->hasPrototype()) {
                $prototype    = $this->getPrototype();
                $protoString .= ", prototype {$prototype->getDeclaringClass()->name}";
            }
        } elseif ($this->hasPrototype()) {
            $prototype      = $this->getPrototype();
            $prototypeClass = $prototype->getDeclaringClass()->name;
            $parentClass    = $this->getDeclaringClass()->getParentClass();
            // If we have the same method in parent, then we override it as well, otherwise it is prototype
            $overrideProto = $parentClass && $parentClass->hasMethod($this->getName());
            if ($overrideProto) {
                $protoString .= ", overwrites {$prototypeClass}";
            }
            $protoString .= ", prototype {$prototypeClass}";
        }

        $fileString = '';
        if ($this->getFileName()) {
            $fileString .= "\n  @@ " . $this->getFileName();
            $fileString .= ' ' . $this->getStartLine();
            $fileString .= ' - ' . $this->getEndLine();
        }

        $paramString = '';
        $indentation = str_repeat(' ', 4);
        foreach ($methodParameters as $methodParameter) {
            $paramString .= "\n{$indentation}" . $methodParameter;
        }

        return sprintf(
            "%sMethod [ <%s%s%s>%s%s%s %s method %s ] {%s{$paramFormat}{$returnFormat}\n}\n",
            $this->getDocComment() ? $this->getDocComment() . "\n" : '',
            $this->isInternal() ? 'internal' : 'user',
            $protoString,
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
            $fileString,
            count($methodParameters),
            $paramString,
            $returnType ? ReflectionType::convertToDisplayType($returnType) : ''
        );
    }

    /**
     * {@inheritDoc}
     */
    public function getClosure($object = null): \Closure
    {
        $this->initializeInternalReflection();

        return parent::getClosure($object);
    }

    /**
     * {@inheritDoc}
     *
     * @return ReflectionClass|ReflectionEnum
     */
    public function getDeclaringClass(): \ReflectionClass
    {
        return $this->declaringClass ?? new ReflectionClass($this->className);
    }

    /**
     * Checks if this method is an Enum magic method (cases/from/tryFrom).
     */
    private function isEnumMagicMethod(): bool
    {
        return $this->getDeclaringClass()->isEnum()
            && in_array($this->getName(), ['cases', 'tryFrom', 'from'], true);
    }

    /**
     * {@inheritDoc}
     */
    public function isInternal(): bool
    {
        return $this->isEnumMagicMethod();
    }

    /**
     * {@inheritDoc}
     */
    public function isUserDefined(): bool
    {
        return !$this->isEnumMagicMethod();
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
    public function getPrototype(): \ReflectionMethod
    {
        $allKnownParents = [];

        $parent = $this->getDeclaringClass()->getParentClass();
        if ($parent instanceof \ReflectionClass) {
            $allKnownParents[] = $parent;
        }
        $allKnownParents = array_merge($allKnownParents, $this->getDeclaringClass()->getInterfaces());
        $methodName      = $this->getName();
        foreach ($allKnownParents as $knownParent) {
            if ($knownParent->hasMethod($methodName)) {
                return $knownParent->getMethod($methodName);
            }
        }

        throw new ReflectionException("Method " . $this->getDeclaringClass()->getName() . "::" . $methodName . "() does not have prototype");
    }

    public function hasPrototype(): bool
    {
        $allKnownParents = [];

        $parent = $this->getDeclaringClass()->getParentClass();
        if ($parent instanceof \ReflectionClass) {
            $allKnownParents[] = $parent;
        }
        $allKnownParents = array_merge($allKnownParents, $this->getDeclaringClass()->getInterfaces());
        foreach ($allKnownParents as $knownParent) {
            if ($knownParent->hasMethod($this->getName())) {
                return true;
            }
        }

        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function invoke(?object $object, mixed ...$args): mixed
    {
        $this->initializeInternalReflection();

        return parent::invoke($object, ...$args);
    }

    /**
     * {@inheritDoc}
     *
     * @param array<int, mixed> $args
     */
    public function invokeArgs(?object $object, array $args): mixed
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
    #[\Deprecated("Usage of ReflectionMethod::setAccessible() has no effect.", since: "8.1")]
    public function setAccessible(bool $accessible): void
    {
    }

    /**
     * Parses methods from the concrete class node
     *
     * @param ClassLike $classLikeNode Class-like node
     * @param ReflectionClass|ReflectionEnum $reflectionClass Reflection of the class
     *
     * @return array<string, ReflectionMethod>
     */
    public static function collectFromClassNode(ClassLike $classLikeNode, ReflectionClass|ReflectionEnum $reflectionClass): array
    {
        $methods = [];

        foreach ($classLikeNode->stmts as $classLevelNode) {
            if ($classLevelNode instanceof ClassMethod) {
                $classLevelNode->setAttribute('fileName', $classLikeNode->getAttribute('fileName'));

                $methodName = $classLevelNode->name->toString();
                $methods[$methodName] = new ReflectionMethod(
                    $reflectionClass->name,
                    $methodName,
                    $classLevelNode,
                    $reflectionClass
                );
            }
        }

        // Enum has special `cases` (and `from`/`tryFrom` for Backed Enums) methods
        if ($classLikeNode instanceof Enum_) {
            $methods['cases'] = self::createEnumCasesMethod($reflectionClass);
            // Backed enum methods emulation
            if (isset($classLikeNode->scalarType)) {
                $methods['from']    = self::createEnumFromMethod($reflectionClass);
                $methods['tryFrom'] = self::createEnumTryFromMethod($reflectionClass);
            }
        }

        return $methods;
    }

    /**
     * Implementation of internal reflection initialization
     */
    protected function __initialize(): void
    {
        parent::__construct($this->className, $this->getName());
    }

    /**
     * Ad-Hoc constructor of Enum `cases` method, which emulates PHP behaviour
     */
    private static function createEnumCasesMethod(ReflectionClass|ReflectionEnum $reflectionClass): ReflectionMethod
    {
        $casesMethodNode = (new \PhpParser\Builder\Method('cases'))
            ->makeStatic()
            ->makePublic()
            ->setReturnType('array')
            ->getNode();
        
        return new self(
            $reflectionClass->name,
            'cases',
            $casesMethodNode,
            $reflectionClass
        );
    }

    private static function createEnumFromMethod(ReflectionClass|ReflectionEnum $reflectionClass): ReflectionMethod
    {
        $valueParam = (new \PhpParser\Builder\Param('value'))
            ->setType(new UnionType([new Identifier('string'), new Identifier('int')]))
            ->getNode();
        $fromMethodNode = (new \PhpParser\Builder\Method('from'))
            ->makeStatic()
            ->makePublic()
            ->addParam($valueParam)
            ->setReturnType('static')
            ->getNode();

        return new self(
            $reflectionClass->name,
            'from',
            $fromMethodNode,
            $reflectionClass
        );
    }

    private static function createEnumTryFromMethod(ReflectionClass|ReflectionEnum $reflectionClass): ReflectionMethod
    {
        $valueParam = (new \PhpParser\Builder\Param('value'))
            ->setType(new UnionType([new Identifier('string'), new Identifier('int')]))
            ->getNode();
        $fromMethodNode = (new \PhpParser\Builder\Method('tryFrom'))
            ->makeStatic()
            ->makePublic()
            ->addParam($valueParam)
            ->setReturnType('?static')
            ->getNode();

        return new self(
            $reflectionClass->name,
            'tryFrom',
            $fromMethodNode,
            $reflectionClass
        );
    }
    
    /**
     * Returns ClassMethod node to prevent all possible type checks with instanceof
     */
    private function getClassMethodNode(): ClassMethod
    {
        if (!$this->functionLikeNode instanceof ClassMethod) {
            throw new \LogicException('Expected ClassMethod node');
        }

        return $this->functionLikeNode;
    }
}
