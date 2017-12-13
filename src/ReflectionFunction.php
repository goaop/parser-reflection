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
class ReflectionFunction extends BaseReflectionFunction implements ReflectionInterface
{
    use ReflectionFunctionLikeTrait, InternalPropertiesEmulationTrait;

    /**
     * Name of the function
     *
     * @var string|\Closure
     */
    private $functionName;

    /**
     * Initializes reflection instance for given AST-node
     *
     * @param string|\Closure $functionName The name of the function to reflect or a closure.
     * @param Function_|null  $functionNode Function node AST
     */
    public function __construct($functionName, Function_ $functionNode = null)
    {
        $this->functionName = $functionName;
        $namespaceParts     = explode('\\', $functionName);
        // Remove the last one part with function name
        $shortName = array_pop($namespaceParts);
        $this->namespaceName = join('\\', $namespaceParts);
        $this->functionLikeNode = $functionNode;
        // Let's unset original read-only property to have a control over it via __get
        unset($this->name);

        if ($this->isParsedNodeMissing()) {
            // This will be implemented later:
            // $this->functionLikeNode = ReflectionEngine::parseFunction($functionName);
            throw new \InvalidArgumentException("PhpParser\\Node for function {$functionName}() must be provided.");
        }
        if ($this->functionLikeNode && ($shortName !== $this->functionLikeNode->name)) {
            throw new \InvalidArgumentException("PhpParser\\Node\\Stmt\\Function_'s name does not match provided function name.");
        }
    }

    /**
     * Are we missing the parser node?
     */
    private function isParsedNodeMissing()
    {
        if ($this->functionLikeNode) {
            return false;
        }
        $isUserDefined = true;
        if ($this->wasIncluded()) {
            $nativeRef = new BaseReflectionFunction($this->getName());
            $isUserDefined = $nativeRef->isUserDefined();
        }
        return $isUserDefined;
    }

    /**
     * Emulating original behaviour of reflection
     */
    public function ___debugInfo()
    {
        $nodeName = 'unknown';

        if ($this->functionLikeNode instanceof Function_) {
            $nodeName = $this->functionLikeNode->name;
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
        if (!$this->functionLikeNode) {
            $this->initializeInternalReflection();
            return parent::isDisabled();
        }
        return false;
    }

    /**
     * Returns textual representation of function
     *
     * @return string
     */
    public function __toString()
    {
        $origin = 'user';
        $source = '';
        if (!$this->isUserDefined()) {
            $origin = 'internal';
            if ($this->isDeprecated()) {
                $origin .= ', deprecated';
            }
            $phpExt = $this->getExtension();
            if ($phpExt) {
                $origin .= ':' . $phpExt->getName();
            }
        } else {
            $source = sprintf("\n  @@ %s %d - %d", $this->getFileName(), $this->getStartLine(), $this->getEndLine());
        }
        // Internally $this->getReturnType() !== null is the same as $this->hasReturnType()
        $returnType    = $this->getReturnType();
        $hasReturnType = $returnType !== null;
        $paramFormat   = '';
        $returnFormat  = '';
        if (($this->getNumberOfParameters() > 0) || $hasReturnType) {
            $paramFormat  = "\n\n  - Parameters [%d] {%s\n  }";
            $returnFormat = $hasReturnType ? "\n  - Return [ %s ]" : '';
        }
        $reflectionFormat = "%sFunction [ <%s> function %s ] {%s{$paramFormat}{$returnFormat}\n}\n";

        return sprintf(
            $reflectionFormat,
            $this->getDocComment() ? $this->getDocComment() . "\n" : '',
            $origin,
            $this->getName(),
            $source,
            count($this->getParameters()),
            array_reduce(
                $this->getParameters(),
                (function ($str, ReflectionParameter $param) {
                    return $str . "\n    " . $param;
                }),
                ''
            ),
            $returnType ? ReflectionType::convertToDisplayType($returnType) : ''
        );
    }


    /**
     * Implementation of internal reflection initialization
     *
     * @return void
     */
    protected function __initialize()
    {
        parent::__construct($this->functionName);
    }

    /**
     * Has function been loaded by PHP.
     *
     * @return bool
     *     If file containing function was included.
     */
    public function wasIncluded()
    {
        return function_exists($this->functionName);
    }
}
