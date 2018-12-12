<?php
/**
 * Parser Reflection API
 *
 * @copyright Copyright 2015, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\ParserReflection;

use Go\ParserReflection\Traits\InternalPropertiesEmulationTrait;
use Go\ParserReflection\Traits\ReflectionFunctionLikeTrait;
use PhpParser\Node\Stmt\Function_;
use ReflectionFunction as BaseReflectionFunction;

/**
 * AST-based reflection for function
 */
class ReflectionFunction extends BaseReflectionFunction
{
    use ReflectionFunctionLikeTrait, InternalPropertiesEmulationTrait;

    /**
     * Initializes reflection instance for given AST-node
     *
     * @param string|\Closure $functionName The name of the function to reflect or a closure.
     * @param Function_|null  $functionNode Function node AST
     */
    public function __construct($functionName, Function_ $functionNode)
    {
        $namespaceParts = explode('\\', $functionName);
        // Remove the last one part with function name
        array_pop($namespaceParts);
        $this->namespaceName = join('\\', $namespaceParts);

        $this->functionLikeNode = $functionNode;
        unset($this->name);
    }

    /**
     * Emulating original behaviour of reflection
     */
    public function ___debugInfo()
    {
        $nodeName = 'unknown';

        if ($this->functionLikeNode instanceof Function_) {
            $nodeName = $this->functionLikeNode->name->toString();
        }

        return ['name' => $nodeName];
    }

    /**
     * Returns an AST-node for function
     *
     * @return Function_
     */
    public function getNode()
    {
        return $this->functionLikeNode;
    }

    /**
     * {@inheritDoc}
     */
    public function getClosure()
    {
        $this->initializeInternalReflection();

        return parent::getClosure();
    }

    /**
     * {@inheritDoc}
     */
    public function invoke($args = null)
    {
        $this->initializeInternalReflection();

        return call_user_func_array('parent::invoke', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function invokeArgs(array $args)
    {
        $this->initializeInternalReflection();

        return parent::invokeArgs($args);
    }

    /**
     * Checks if function is disabled
     *
     * Only internal functions can be disabled using disable_functions directive.
     * User-defined functions are unaffected.
     */
    public function isDisabled()
    {
        return false;
    }

    /**
     * Returns textual representation of function
     *
     * @return string
     */
    public function __toString()
    {
        $paramFormat      = ($this->getNumberOfParameters() > 0) ? "\n\n  - Parameters [%d] {%s\n  }" : '';
        $reflectionFormat = "%sFunction [ <user> function %s ] {\n  @@ %s %d - %d{$paramFormat}\n}\n";

        return sprintf(
            $reflectionFormat,
            $this->getDocComment() ? $this->getDocComment() . "\n" : '',
            $this->getName(),
            $this->getFileName(),
            $this->getStartLine(),
            $this->getEndLine(),
            count($this->getParameters()),
            array_reduce($this->getParameters(), function ($str, ReflectionParameter $param) {
                return $str . "\n    " . $param;
            }, '')
        );
    }


    /**
     * Implementation of internal reflection initialization
     *
     * @return void
     */
    protected function __initialize()
    {
        parent::__construct($this->getName());
    }
}
