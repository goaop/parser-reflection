<?php

declare(strict_types=1);
/**
 * Parser Reflection API
 *
 * @copyright Copyright 2026, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\ParserReflection;

use PhpParser\Node;

/**
 * Interface for parsed reflections that are aware of their underlying AST node
 *
 * Implementations narrow the return type of getNode() via covariance to expose
 * the concrete node type they wrap (e.g. ClassLike, ClassMethod, Param, etc.)
 */
interface NodeAwareInterface
{
    /**
     * Returns the underlying AST node for this reflection
     */
    public function getNode(): Node;
}
