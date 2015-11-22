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

use Go\ParserReflection\Traits\ReflectionFunctionLikeTrait;
use PhpParser\Node\Stmt\Function_;
use ReflectionFunction as BaseReflectionFunction;

/**
 * AST-based reflection for function
 */
class ReflectionFunction extends BaseReflectionFunction
{
    use ReflectionFunctionLikeTrait;

    /**
     * Initializes reflection instance for given AST-node
     *
     * @param string|\Closure $functionName The name of the function to reflect or a closure.
     * @param Function_|null  $functionNode Function node AST
     */
    public function __construct($functionName, Function_ $functionNode)
    {
        $namespaceParts      = explode('\\', $functionName);
        $this->funcName      = array_pop($namespaceParts);
        $this->namespaceName = join('\\', $namespaceParts);

        $this->functionLikeNode = $functionNode;
    }

    /**
     * Emulating original behaviour of reflection
     */
    public function __debugInfo()
    {
        return array(
            'name'  => $this->functionLikeNode->name,
        );
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
        if (!$this->isInternal()) {
            return false;
        }

        $disabledFunctions = explode(',', ini_get('disable_functions'));
        $disabledFunctions = array_map('trim', $disabledFunctions);

        return in_array($this->getName(), $disabledFunctions);
    }

    /**
     * Returns textual representation of function
     *
     * @return string
     */
    public function __toString()
    {
        $paramFormat      = ($this->getNumberOfParameters() > 0) ? "\n\n  - Parameters [%d] {%s\n  }" : '';
        $reflectionFormat = "Function [ <user> function %s ] {\n  @@ %s %d - %d{$paramFormat}\n}\n";

        return sprintf(
            $reflectionFormat,
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