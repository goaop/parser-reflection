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

use Closure;
use Go\ParserReflection\Traits\InternalPropertiesEmulationTrait;
use Go\ParserReflection\Traits\ReflectionFunctionLikeTrait;
use PhpParser\Node\Stmt\Function_;
use ReflectionFunction as BaseReflectionFunction;

/**
 * AST-based reflection for function
 */
class ReflectionFunction extends BaseReflectionFunction
{
    use InternalPropertiesEmulationTrait;
    use ReflectionFunctionLikeTrait;

    /**
     * Initializes reflection instance for given AST-node
     *
     * @param string|Closure $functionName The name of the function to reflect or a closure.
     * @param Function_ $functionNode Function node AST
     */
    public function __construct($functionName, Function_ $functionNode)
    {
        $namespaceParts = explode('\\', $functionName);
        // Remove the last one part with function name
        array_pop($namespaceParts);
        $this->namespaceName = implode('\\', $namespaceParts);

        $this->functionLikeNode = $functionNode;
        unset($this->name);
    }

    /**
     * Emulating original behaviour of reflection
     */
    public function __debugInfo(): array
    {
        $nodeName = 'unknown';

        if ($this->functionLikeNode instanceof Function_) {
            $nodeName = $this->functionLikeNode->name->toString();
        }

        return ['name' => $nodeName];
    }

    /**
     * Returns an AST-node for function
     */
    public function getNode(): Function_
    {
        return $this->functionLikeNode;
    }

    /**
     * {@inheritDoc}
     */
    public function getClosure(): \Closure
    {
        $this->initializeInternalReflection();

        return parent::getClosure();
    }

    /**
     * {@inheritDoc}
     */
    public function invoke(mixed ...$args)
    {
        $this->initializeInternalReflection();

        return parent::invoke(...$args);
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
    public function isDisabled(): bool
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
            array_reduce($this->getParameters(), static function ($str, ReflectionParameter $param) {
                return $str . "\n    " . $param;
            }, '')
        );
    }


    /**
     * Implementation of internal reflection initialization
     */
    protected function __initialize(): void
    {
        parent::__construct($this->getName());
    }
}
