<?php
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
use Go\ParserReflection\ReflectionParameter;
use Go\ParserReflection\ReflectionType;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Identifier;
use PhpParser\Node\NullableType;
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
    public function getClosureScopeClass()
    {
        $this->initializeInternalReflection();

        return parent::getClosureScopeClass();
    }

    /**
     * {@inheritDoc}
     */
    public function getClosureThis()
    {
        $this->initializeInternalReflection();

        return parent::getClosureThis();
    }

    public function getDocComment()
    {
        $docComment = $this->functionLikeNode->getDocComment();

        return $docComment ? $docComment->getText() : false;
    }

    public function getEndLine()
    {
        return $this->functionLikeNode->getAttribute('endLine');
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
        return $this->functionLikeNode->getAttribute('fileName');
    }

    /**
     * {@inheritDoc}
     */
    public function getName()
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
    public function getNamespaceName()
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
    public function getNumberOfParameters()
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
    public function getNumberOfRequiredParameters()
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
    public function getParameters()
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
    public function getReturnType()
    {
        $isBuiltin  = false;
        $returnType = $this->functionLikeNode->getReturnType();
        $isNullable = $returnType instanceof NullableType;

        if ($isNullable) {
            $returnType = $returnType->type;
        }
        if ($returnType instanceof Identifier) {
            $isBuiltin = true;
            $returnType = $returnType->toString();
        } elseif (is_object($returnType)) {
            $returnType = $returnType->toString();
        } elseif (is_string($returnType)) {
            $isBuiltin = true;
        } else {
            return null;
        }

        return new ReflectionType($returnType, $isNullable, $isBuiltin);
    }

    /**
     * {@inheritDoc}
     */
    public function getShortName()
    {
        if ($this->functionLikeNode instanceof Function_ || $this->functionLikeNode instanceof ClassMethod) {
            return $this->functionLikeNode->name->toString();
        }

        return false;
    }

    public function getStartLine()
    {
        return $this->functionLikeNode->getAttribute('startLine');
    }

    /**
     * {@inheritDoc}
     */
    public function getStaticVariables()
    {
        // In nikic/PHP-Parser < 2.0.0 the default behavior is cloning
        //     nodes when traversing them. Passing FALSE to the constructor
        //     prevents this.
        // In nikic/PHP-Parser >= 2.0.0 and < 3.0.0 the default behavior was
        //     changed to not clone nodes, but the parameter was retained as
        //     an option.
        // In nikic/PHP-Parser >= 3.0.0 the option to clone nodes was removed
        //     as a constructor parameter, so Scrutinizer will pick this up as
        //     an issue. It is retained for legacy compatibility.
        $nodeTraverser      = new NodeTraverser(false);
        $variablesCollector = new StaticVariablesCollector($this);
        $nodeTraverser->addVisitor($variablesCollector);

        /* @see https://github.com/nikic/PHP-Parser/issues/235 */
        $nodeTraverser->traverse($this->functionLikeNode->getStmts() ?: array());

        return $variablesCollector->getStaticVariables();
    }

    /**
     * Checks if the function has a specified return type
     *
     * @return bool
     *
     * @link http://php.net/manual/en/reflectionfunctionabstract.hasreturntype.php
     */
    public function hasReturnType()
    {
        $returnType = $this->functionLikeNode->getReturnType();

        return isset($returnType);
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
    public function isClosure()
    {
        return $this->functionLikeNode instanceof Closure;
    }

    /**
     * {@inheritDoc}
     */
    public function isDeprecated()
    {
        // userland method/function/closure can not be deprecated
        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function isGenerator()
    {
        // In nikic/PHP-Parser < 2.0.0 the default behavior is cloning
        //     nodes when traversing them. Passing FALSE to the constructor
        //     prevents this.
        // In nikic/PHP-Parser >= 2.0.0 and < 3.0.0 the default behavior was
        //     changed to not clone nodes, but the parameter was retained as
        //     an option.
        // In nikic/PHP-Parser >= 3.0.0 the option to clone nodes was removed
        //     as a constructor parameter, so Scrutinizer will pick this up as
        //     an issue. It is retained for legacy compatibility.
        $nodeTraverser = new NodeTraverser(false);
        $nodeDetector  = new GeneratorDetector();
        $nodeTraverser->addVisitor($nodeDetector);

        /* @see https://github.com/nikic/PHP-Parser/issues/235 */
        $nodeTraverser->traverse($this->functionLikeNode->getStmts() ?: array());

        return $nodeDetector->isGenerator();
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
    public function isUserDefined()
    {
        // always defined by user, because we parse the source code
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function isVariadic()
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
    public function returnsReference()
    {
        return $this->functionLikeNode->returnsByRef();
    }
}
