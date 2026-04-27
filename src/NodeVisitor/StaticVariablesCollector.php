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

namespace Go\ParserReflection\NodeVisitor;

use Go\ParserReflection\ReflectionFileNamespace;
use Go\ParserReflection\Resolver\NodeExpressionResolver;
use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

/**
 * Visitor to collect static variables in the method/function body and resolve them
 */
class StaticVariablesCollector extends NodeVisitorAbstract
{
    /**
     * Reflection context, eg. ReflectionClass, ReflectionMethod, etc
     *
     * @var \ReflectionClass<object>|\ReflectionFunction|\ReflectionMethod|\ReflectionClassConstant|\ReflectionParameter|\ReflectionAttribute<object>|\ReflectionProperty|ReflectionFileNamespace|null
     */
    private \ReflectionClass|\ReflectionFunction|\ReflectionMethod|\ReflectionClassConstant|\ReflectionParameter|\ReflectionAttribute|\ReflectionProperty|ReflectionFileNamespace|null $context;

    /**
     * @var array<string, mixed>
     */
    private array $staticVariables = [];

    /**
     * Default constructor
     *
     * @param \ReflectionClass<object>|\ReflectionFunction|\ReflectionMethod|\ReflectionClassConstant|\ReflectionParameter|\ReflectionAttribute<object>|\ReflectionProperty|ReflectionFileNamespace|null $context Reflection context, eg. ReflectionClass, ReflectionMethod, etc
     */
    public function __construct(\ReflectionClass|\ReflectionFunction|\ReflectionMethod|\ReflectionClassConstant|\ReflectionParameter|\ReflectionAttribute|\ReflectionProperty|ReflectionFileNamespace|null $context)
    {
        $this->context = $context;
    }

    /**
     * {@inheritDoc}
     */
    public function enterNode(Node $node)
    {
        // There may be internal closures, we do not need to look at them
        if ($node instanceof Node\Expr\Closure) {
            return self::DONT_TRAVERSE_CHILDREN;
        }

        if ($node instanceof Node\Stmt\Static_) {
            $expressionSolver = new NodeExpressionResolver($this->context);
            $staticVariables  = $node->vars;
            foreach ($staticVariables as $staticVariable) {
                $expr = $staticVariable->default;
                if ($expr) {
                    $expressionSolver->process($expr);
                    $value = $expressionSolver->getValue();
                } else {
                    $value = null;
                }

                if ($staticVariable->var->name instanceof Node\Expr) {
                    $expressionSolver->process($staticVariable->var->name);
                    $resolvedName = $expressionSolver->getValue();
                    if (!is_string($resolvedName)) {
                        throw new \InvalidArgumentException("Unknown value for the key, " . gettype($resolvedName) . " has given, but string is expected");
                    }
                    $name = $resolvedName;
                } else {
                    $name = $staticVariable->var->name;
                }
                $this->staticVariables[$name] = $value;
            }
        }

        return null;
    }

    /**
     * Returns an associative map of static variables in the method/function body
     *
     * @return array<string, mixed>
     */
    public function getStaticVariables(): array
    {
        return $this->staticVariables;
    }
}
