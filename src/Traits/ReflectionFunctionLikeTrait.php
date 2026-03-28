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


use Go\ParserReflection\NodeVisitor\GeneratorDetector;
use Go\ParserReflection\NodeVisitor\StaticVariablesCollector;
use Go\ParserReflection\ReflectionMethod;
use Go\ParserReflection\ReflectionParameter;
use Go\ParserReflection\Resolver\TypeExpressionResolver;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Identifier;
use PhpParser\Node\NullableType;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;
use PhpParser\NodeTraverser;
use ReflectionException;
use ReflectionExtension;

/**
 * General trait for all function-like reflections
 */
trait ReflectionFunctionLikeTrait
{
    use InitializationTrait;

    protected FunctionLike|Function_|ClassMethod $functionLikeNode;

    /**
     * Namespace name
     */
    protected string $namespaceName = '';

    /**
     * @var ReflectionParameter[]
     */
    protected array $parameters;

    /**
     * {@inheritDoc}
     *
     * @return \ReflectionClass<object>|null
     */
    public function getClosureScopeClass(): ?\ReflectionClass
    {
        $this->initializeInternalReflection();

        return parent::getClosureScopeClass();
    }

    /**
     * {@inheritDoc}
     */
    public function getClosureThis(): ?object
    {
        $this->initializeInternalReflection();

        return parent::getClosureThis();
    }

    public function getDocComment(): string|false
    {
        $docComment = $this->functionLikeNode->getDocComment();

        return $docComment ? $docComment->getText() : false;
    }

    public function getEndLine(): int|false
    {
        $endLine = $this->functionLikeNode->getAttribute('endLine');

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
        $fileName = $this->functionLikeNode->getAttribute('fileName');

        return is_string($fileName) ? $fileName : false;
    }

    /**
     * {@inheritDoc}
     */
    public function getName(): string
    {
        if ($this->functionLikeNode instanceof Function_ || $this->functionLikeNode instanceof ClassMethod) {
            $functionName = $this->functionLikeNode->name->toString();

            return $this->namespaceName ? $this->namespaceName . '\\' . $functionName : $functionName;
        }

        return '';
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
     */
    public function getNumberOfParameters(): int
    {
        return count($this->functionLikeNode->getParams());
    }

    /**
     * Get the number of required parameters that a function defines.
     *
     * @link http://php.net/manual/en/reflectionfunctionabstract.getnumberofrequiredparameters.php
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
     * {@inheritDoc}
     */
    public function getParameters(): array
    {
        if (!isset($this->parameters)) {
            $parameters = [];

            foreach ($this->functionLikeNode->getParams() as $parameterIndex => $parameterNode) {
                $reflectionParameter = new ReflectionParameter(
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
     * Gets the specified return type of a function
     *
     * @link http://php.net/manual/en/reflectionfunctionabstract.getreturntype.php
     */
    public function getReturnType(): \ReflectionNamedType|\ReflectionUnionType|\ReflectionIntersectionType|null
    {
        $returnType = $this->functionLikeNode->getReturnType();
        if ($this->hasReturnType() && $returnType !== null) {
            $typeResolver = new TypeExpressionResolver();
            $typeResolver->process($returnType, false);

            return $typeResolver->getType();
        }

        return null;
    }

    /**
     * {@inheritDoc}
     */
    public function getShortName(): string
    {
        if ($this->functionLikeNode instanceof Function_ || $this->functionLikeNode instanceof ClassMethod) {
            return $this->functionLikeNode->name->toString();
        }

        throw new ReflectionException('unable to get short name');
    }

    public function getStartLine(): int|false
    {
        if ($this->functionLikeNode->getAttrGroups() !== []) {
            $attrGroups = $this->functionLikeNode->getAttrGroups();
            $lastAttrGroupsEndLine = end($attrGroups)->getAttribute('endLine');

            return is_int($lastAttrGroupsEndLine) ? $lastAttrGroupsEndLine + 1 : false;
        }

        $startLine = $this->functionLikeNode->getAttribute('startLine');

        return is_int($startLine) ? $startLine : false;
    }

    /**
     * {@inheritDoc}
     *
     * @return array<string, mixed>
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
        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function isUserDefined(): bool
    {
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
}
