<?php
/**
 * Parser Reflection API
 *
 * @copyright Copyright 2015, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace ParserReflection\NodeVisitor;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

/**
 * Visitor to collect static variables in the method/function body and resove them
 */
class StaticVariablesCollector extends NodeVisitorAbstract
{
    private $staticVariables = [];

    /**
     * {@inheritDoc}
     */
    public function enterNode(Node $node)
    {
        if ($node instanceof Node\Stmt\Static_) {
            $staticVariables = $node->vars;
            foreach ($staticVariables as $staticVariable) {
                $expr  = $staticVariable->default;
                $value = null;
                // TODO: Add code evaluator for the expression
                if ($expr instanceof Node\Scalar && isset($expr->value)) {
                    $value = $expr->value;
                }

                $this->staticVariables[$staticVariable->name] = $value;
            }
        }
    }

    /**
     * Returns an associative map of static variables in the method/function body
     *
     * @return array
     */
    public function getStaticVariables()
    {
        return $this->staticVariables;
    }
}
