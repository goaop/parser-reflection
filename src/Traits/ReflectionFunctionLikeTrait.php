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
use Go\ParserReflection\ReflectionNamedType;
use Go\ParserReflection\ReflectionParameter;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Identifier;
use PhpParser\Node\NullableType;
use PhpParser\Node\UnionType;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;
use PhpParser\NodeTraverser;

/**
 * General trait for all function-like reflections
 */
trait ReflectionFunctionLikeTrait
{
    use InitializationTrait;

    /**
     * @var FunctionLike
     */
    protected $functionLikeNode;

    /**
     * Namespace name
     *
     * @var string
     */
    protected $namespaceName = '';

    /**
     * @var array|ReflectionParameter[]
     */
    protected $parameters;

    /**
     * {@inheritDoc}
     */
    #[\ReturnTypeWillChange]
    public function getClosureScopeClass()
    {
        $this->initializeInternalReflection();

        return parent::getClosureScopeClass();
    }

    /**
     * {@inheritDoc}
     */
    #[\ReturnTypeWillChange]
    public function getClosureThis()
    {
        $this->initializeInternalReflection();

        return parent::getClosureThis();
    }

    #[\ReturnTypeWillChange]
    public function getDocComment()
    {
        $docComment = $this->functionLikeNode->getDocComment();

        return $docComment ? $docComment->getText() : false;
    }

    #[\ReturnTypeWillChange]
    public function getEndLine()
    {
        return $this->functionLikeNode->getAttribute('endLine');
    }

    #[\ReturnTypeWillChange]
    public function getExtension()
    {
        return null;
    }

    #[\ReturnTypeWillChange]
    public function getExtensionName()
    {
        return false;
    }

    #[\ReturnTypeWillChange]
    public function getFileName()
    {
        return $this->functionLikeNode->getAttribute('fileName');
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

        return false;
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
     * {@inheritDoc}
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
     * Gets the specified return type of a function
     *
     * @return \ReflectionType
     *
     * @link http://php.net/manual/en/reflectionfunctionabstract.getreturntype.php
     */
    #[\ReturnTypeWillChange]
    public function getReturnType()
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
        } elseif ($returnType instanceof UnionType) {
            $returnType = implode('|', $returnType->types);
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
     * {@inheritDoc}
     */
    public function getShortName(): string
    {
        if ($this->functionLikeNode instanceof Function_ || $this->functionLikeNode instanceof ClassMethod) {
            return $this->functionLikeNode->name->toString();
        }

        return false;
    }

    #[\ReturnTypeWillChange]
    public function getStartLine()
    {
        return $this->functionLikeNode->getAttribute('startLine');
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
}
