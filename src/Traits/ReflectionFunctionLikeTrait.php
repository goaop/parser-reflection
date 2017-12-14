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
use Go\ParserReflection\ReflectionExtension;
use ReflectionParameter as BaseReflectionParameter;
use Go\ParserReflection\ReflectionType;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Name\FullyQualified as FullyQualifiedName;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Builder\Param as ParamNodeBuilder;
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
        if (!$this->functionLikeNode) {
            $this->initializeInternalReflection();
            return parent::getDocComment();
        }
        $docComment = $this->functionLikeNode->getDocComment();

        return $docComment ? $docComment->getText() : false;
    }

    public function getEndLine()
    {
        if (!$this->functionLikeNode) {
            $this->initializeInternalReflection();
            return parent::getEndLine();
        }
        return $this->functionLikeNode->getAttribute('endLine');
    }

    public function getExtension()
    {
        $extName = $this->getExtensionName();
        if (!$extName) {
            return null;
        }
        // The purpose of Go\ParserReflection\ReflectionExtension is
        // to behave exactly like \ReflectionExtension, but return
        // Go\ParserReflection\ReflectionFunction and
        // Go\ParserReflection\ReflectionClass where apropriate.
        return new ReflectionExtension($extName);
    }

    public function getExtensionName()
    {
        if (!$this->functionLikeNode) {
            $this->initializeInternalReflection();
            return parent::getExtensionName();
        }
        return false;
    }

    public function getFileName()
    {
        if (!$this->functionLikeNode) {
            // If we got here, we're probably a built-in method/function, and
            // filename is probably false.
            $this->initializeInternalReflection();
            return parent::getFileName();
        }
        return $this->functionLikeNode->getAttribute('fileName');
    }

    /**
     * {@inheritDoc}
     */
    public function getName()
    {
        if (!$this->functionLikeNode) {
            $this->initializeInternalReflection();
            return parent::getName();
        }
        if ($this->functionLikeNode instanceof Function_ || $this->functionLikeNode instanceof ClassMethod) {
            $functionName = $this->functionLikeNode->name;

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
        if (!$this->functionLikeNode) {
            $this->initializeInternalReflection();
            return parent::getNumberOfParameters();
        }
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

            if ($this->functionLikeNode) {
                foreach ($this->functionLikeNode->getParams() as $parameterIndex => $parameterNode) {
                    $reflectionParameter = new ReflectionParameter(
                        $this->getName(),
                        $parameterNode->name,
                        $parameterNode,
                        $parameterIndex,
                        $this
                    );
                    $parameters[] = $reflectionParameter;
                }
            } else {
                $this->initializeInternalReflection();
                $nativeParamRefs = parent::getParameters();
                foreach ($nativeParamRefs as $parameterIndex => $parameterNode) {
                    $parameters[$parameterIndex] = $this->getRefParam($parameterNode);
                }
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
        if ($this->functionLikeNode) {
            $returnType = $this->functionLikeNode->getReturnType();
            $isNullable = $returnType instanceof NullableType;

            if ($isNullable) {
                $returnType = $returnType->type;
            }
            if (is_object($returnType)) {
                $returnType = $returnType->toString();
            } elseif (is_string($returnType)) {
                $isBuiltin = true;
            } else {
                return null;
            }
        } else {
            if (PHP_VERSION_ID < 70000) {
                return null;
            }
            $this->initializeInternalReflection();
            $nativeType = parent::getReturnType();
            if (!$nativeType) {
                return null;
            }
            $isNullable = $nativeType->allowsNull();
            $isBuiltin = $nativeType->isBuiltin();
            $returnType = (string)$nativeType;
        }

        return new ReflectionType($returnType, $isNullable, $isBuiltin);
    }

    /**
     * {@inheritDoc}
     */
    public function getShortName()
    {
        if (!$this->functionLikeNode) {
            $this->initializeInternalReflection();
            return parent::getShortName();
        }
        if ($this->functionLikeNode instanceof Function_ || $this->functionLikeNode instanceof ClassMethod) {
            return $this->functionLikeNode->name;
        }

        return false;
    }

    public function getStartLine()
    {
        if (!$this->functionLikeNode) {
            $this->initializeInternalReflection();
            return parent::getStartLine();
        }
        return $this->functionLikeNode->getAttribute('startLine');
    }

    /**
     * {@inheritDoc}
     */
    public function getStaticVariables()
    {
        if (!$this->functionLikeNode) {
            $this->initializeInternalReflection();
            return parent::getStaticVariables();
        }
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
        if (!$this->functionLikeNode) {
            $this->initializeInternalReflection();
            return parent::hasReturnType();
        }
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
        if (!$this->functionLikeNode) {
            $this->initializeInternalReflection();
            return parent::isClosure();
        }
        return $this->functionLikeNode instanceof Closure;
    }

    /**
     * {@inheritDoc}
     */
    public function isDeprecated()
    {
        if (!$this->functionLikeNode) {
            $this->initializeInternalReflection();
            return parent::isDeprecated();
        }
        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function isGenerator()
    {
        if (!$this->functionLikeNode) {
            $this->initializeInternalReflection();
            return parent::isGenerator();
        }
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
        if (!$this->functionLikeNode) {
            $this->initializeInternalReflection();
            return parent::isInternal();
        }
        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function isUserDefined()
    {
        if (!$this->functionLikeNode) {
            $this->initializeInternalReflection();
            return parent::isUserDefined();
        }
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
        if (!$this->functionLikeNode) {
            $this->initializeInternalReflection();
            return parent::returnsReference();
        }
        return $this->functionLikeNode->returnsByRef();
    }

    private function getRefParam(BaseReflectionParameter $orig)
    {
        $nullableImplied = false;
        $builder = new ParamNodeBuilder($orig->name);
        if ($orig->isDefaultValueAvailable() || $orig->isOptional()) {
            if ($orig->isDefaultValueAvailable()) {
                $defaultValueAsCodeString = var_export($orig->getDefaultValue(), true);
                if (method_exists($orig, 'isDefaultValueConstant') && $orig->isDefaultValueConstant()) {
                    $defaultValueAsCodeString = $orig->getDefaultValueConstantName();
                }
                $default = ReflectionEngine::parseDefaultValue($defaultValueAsCodeString);
                if (is_null($orig->getDefaultValue())) {
                    $nullableImplied = true;
                }
            } else {
                $default = new ConstFetch(new Name('null'), ['implied' => true]);
                $nullableImplied = true;
            }
            $builder->setDefault($default);
        }
        if ($orig->isPassedByReference()) {
            $builder->makeByRef();
        }
        if (method_exists($orig, 'isVariadic') && $orig->isVariadic()) {
            $builder->makeVariadic();
        }
        if (method_exists($orig, 'hasType') && $orig->hasType()) {
            $typeRef = $orig->getType();
            $stringType = ltrim((string)$typeRef, '?'); // ltrim() is precautionary.
            if (PHP_VERSION_ID >= 70100 && $typeRef->allowsNull()) {
                $stringType = '?' . $stringType;
                $nullableImplied = true;
            }
            $builder->setTypeHint($stringType);
        } else {
            $hintedClass = $orig->getClass();
            if ($hintedClass) {
                $builder->setTypeHint($hintedClass->name);
            } else if ($orig->isArray()) {
                $builder->setTypeHint('array');
            } else if ($orig->isCallable()) {
                $builder->setTypeHint('callable');
            } else {
                $nullableImplied = true;
            }
        }
        $fakeParamNode = $builder->getNode();
        if (!$orig->allowsNull() && $nullableImplied) {
            $fakeParamNode->setAttribute('prohibit_null', true);
        }
        return new ReflectionParameter(
            $this->getName(), // Calling function name:   Unused.
            $orig->name, // Parameter variable name: Unused.
            $fakeParamNode, // Synthetic parse node.
            $orig->getPosition(), // Parameter index.
            $this // Function or method being described.
        );
    }
}
