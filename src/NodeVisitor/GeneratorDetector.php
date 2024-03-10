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

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

/**
 * Visitor to check if the method body
 */
class GeneratorDetector extends NodeVisitorAbstract
{
    private bool $isGenerator = false;

    /**
     * {@inheritDoc}
     */
    public function enterNode(Node $node)
    {
        // There may be internal generators in closures, we do not need to look at them
        if ($node instanceof Node\Expr\Closure) {
            return self::DONT_TRAVERSE_CHILDREN;
        }

        if ($node instanceof Node\Expr\Yield_ || $node instanceof Node\Expr\YieldFrom) {
            $this->isGenerator = true;
        }

        return null;
    }

    public function isGenerator(): bool
    {
        return $this->isGenerator;
    }
}
