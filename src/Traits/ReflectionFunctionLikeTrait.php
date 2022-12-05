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

namespace Go\ParserReflection\Traits;

use Go\ParserReflection\NodeVisitor\GeneratorDetector;
use Go\ParserReflection\NodeVisitor\StaticVariablesCollector;
use Go\ParserReflection\ReflectionAttribute;
use Go\ParserReflection\ReflectionNamedType;
use Go\ParserReflection\ReflectionParameter;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Identifier;
use PhpParser\Node\NullableType;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;
use PhpParser\NodeTraverser;
use ReflectionClass as BaseReflectionClass;
use ReflectionExtension as BaseReflectionExtension;
use ReflectionType as BaseReflectionType;

/**
 * General trait for all function-like reflections
 *
 * @template T of object
 */
trait ReflectionFunctionLikeTrait
{
    use InitializationTrait;
    use CanHoldAttributesTrait;

    /**
     * Function-like node
     *
     * @var FunctionLike
     */
    protected FunctionLike $functionLikeNode;

    /**
     * Namespace name
     *
     * @var string
     */
    protected string $namespaceName = '';

    /**
     * Parameters list
     *
     * @var ReflectionParameter[]
     */
    protected array $parameters;

    /**
     * {@inheritDoc}
     */
    public function getClosureScopeClass(): ?BaseReflectionClass
    {
        $this->initializeInternalReflection();

        /** @noinspection PhpMultipleClassDeclarationsInspection */
        return parent::getClosureScopeClass();
    }

    /**
     * {@inheritDoc}
     */
    public function getClosureThis(): ?object
    {
        $this->initializeInternalReflection();

        /** @noinspection PhpMultipleClassDeclarationsInspection */
        return parent::getClosureThis();
    }

    public function getDocComment(): string|false
    {
        $docComment = $this->functionLikeNode->getDocComment();

        return $docComment ? $docComment->getText() : false;
    }

    public function getEndLine(): int|false
    {
        return $this->functionLikeNode->getAttribute('endLine');
    }

    public function getExtension(): ?BaseReflectionExtension
    {
        return null;
    }

    public function getExtensionName(): string|false
    {
        return false;
    }

    public function getFileName(): string|false
    {
        return $this->functionLikeNode->getAttribute('fileName');
    }

    /**
     * Gets function name
     *
     * @link https://php.net/manual/en/reflectionfunctionabstract.getname.php
     *
     * @return string The name of the function.
     */
    public function getName(): string
    {
        if (isset($this->aliasName)) {
            return $this->aliasName;
        }

        if ($this->functionLikeNode instanceof Function_ || $this->functionLikeNode instanceof ClassMethod) {
            $functionName = $this->functionLikeNode->name->toString();

            return $this->namespaceName ? $this->namespaceName . '\\' . $functionName : $functionName;
        }

        /** @noinspection PhpMultipleClassDeclarationsInspection */
        return parent::getName();
    }

    /**
     * {@inheritDoc}
     */
    public function getNamespaceName(): string
    {
        return $this->namespaceName;
    }

    /**
     * Get the number of parameters that a function defines, both optional and required.
     *
     * @link http://php.net/manual/en/reflectionfunctionabstract.getnumberofparameters.php
     *
     * @return int
     */
    public function getNumberOfParameters(): int
    {
        return count($this->functionLikeNode->getParams());
    }

    /**
     * Get the number of required parameters that a function defines.
     *
     * @link http://php.net/manual/en/reflectionfunctionabstract.getnumberofrequiredparameters.php
     *
     * @return int
     */
    public function getNumberOfRequiredParameters(): int
    {
        $requiredParameters = 0;
        foreach ($this->getParameters() as $parameter) {
            if (!$parameter->isOptional()) {
                $requiredParameters++;
            }
        }

        return $requiredParameters;
    }

    /**
     * Gets parameters
     *
     * @link https://php.net/manual/en/reflectionfunctionabstract.getparameters.php
     *
     * @return ReflectionParameter[] The parameters, as a ReflectionParameter objects.
     */
    public function getParameters(): array
    {
        if (!isset($this->parameters)) {
            $parameters = [];

            foreach ($this->functionLikeNode->getParams() as $parameterIndex => $parameterNode) {
                $reflectionParameter = new ReflectionParameter(
                    $this->getName(),
                    (string)$parameterNode->var->name,
                    $parameterNode,
                    $parameterIndex,
                    $this
                );

                $parameters[] = $reflectionParameter;
            }

            $this->parameters = $parameters;
        }

        return $this->parameters;
    }

    /**
     * {@inheritDoc}
     */
    public function getReturnType(): ?BaseReflectionType
    {
        $isBuiltin  = false;
        $returnType = $this->functionLikeNode->getReturnType();
        $isNullable = $returnType instanceof NullableType;

        if ($isNullable) {
            $returnType = $returnType->type;
        }
        if ($returnType instanceof Identifier) {
            $isBuiltin  = true;
            $returnType = $returnType->toString();
        } elseif (is_object($returnType)) {
            $returnType = $returnType->toString();
        } elseif (is_string($returnType)) {
            $isBuiltin = true;
        } else {
            return null;
        }

        return new ReflectionNamedType($returnType, $isNullable, $isBuiltin);
    }

    /**
     * Gets function short name
     *
     * @link https://php.net/manual/en/reflectionfunctionabstract.getshortname.php
     *
     * @return string The short name of the function.
     */
    public function getShortName(): string
    {
        if (isset($this->aliasName)) {
            return $this->aliasName;
        }

        if ($this->functionLikeNode instanceof Function_ || $this->functionLikeNode instanceof ClassMethod) {
            return $this->functionLikeNode->name->toString();
        }

        /** @noinspection PhpMultipleClassDeclarationsInspection */
        return parent::getShortName();
    }

    public function getStartLine(): int|false
    {
        return $this->functionLikeNode->name->getAttribute('startLine');
    }

    /**
     * {@inheritDoc}
     */
    public function getStaticVariables(): array
    {
        $nodeTraverser      = new NodeTraverser();
        $variablesCollector = new StaticVariablesCollector($this);
        $nodeTraverser->addVisitor($variablesCollector);

        /* @see https://github.com/nikic/PHP-Parser/issues/235 */
        $nodeTraverser->traverse($this->functionLikeNode->getStmts() ?: []);

        return $variablesCollector->getStaticVariables();
    }

    /**
     * Checks if the function has a specified return type
     *
     * @return bool
     *
     * @link http://php.net/manual/en/reflectionfunctionabstract.hasreturntype.php
     */
    public function hasReturnType(): bool
    {
        $returnType = $this->functionLikeNode->getReturnType();

        return isset($returnType);
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
    public function isClosure(): bool
    {
        return $this->functionLikeNode instanceof Closure;
    }

    /**
     * {@inheritDoc}
     */
    public function isDeprecated(): bool
    {
        // user-land method/function/closure can not be deprecated
        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function isGenerator(): bool
    {
        $nodeTraverser = new NodeTraverser();
        $nodeDetector  = new GeneratorDetector();
        $nodeTraverser->addVisitor($nodeDetector);

        /* @see https://github.com/nikic/PHP-Parser/issues/235 */
        $nodeTraverser->traverse($this->functionLikeNode->getStmts() ?: []);

        return $nodeDetector->isGenerator();
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
    public function isUserDefined(): bool
    {
        // always defined by user, because we parse the source code
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function isVariadic(): bool
    {
        foreach ($this->getParameters() as $parameter) {
            if ($parameter->isVariadic()) {
                return true;
            }
        }

        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function returnsReference(): bool
    {
        return $this->functionLikeNode->returnsByRef();
    }

    /**
     * Returns an array of function attributes.
     *
     * @template T
     *
     * @param class-string<T>|null $name  Name of an attribute class
     * @param int                  $flags Criteria by which the attribute is searched.
     *
     * @return ReflectionAttribute<T>[]
     */
    public function getAttributes(?string $name = null, int $flags = 0): array
    {
        if (!isset($this->attributes)) {
            $this->collectAttributes();
        }

        return $this->attributes;
    }
}
