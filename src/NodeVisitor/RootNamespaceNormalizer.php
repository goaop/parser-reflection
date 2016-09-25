<?php
/**
 * Parser Reflection API
 *
 * @copyright Copyright 2016, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\ParserReflection\NodeVisitor;

use PhpParser\Node;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\NodeVisitorAbstract;

/**
 * Visitor to normalize the root namespace for the files without the namespace (root namespace)
 *
 * File->Namespace->Statements
 */
class RootNamespaceNormalizer extends NodeVisitorAbstract
{
    /**
     * {@inheritdoc}
     */
    public function beforeTraverse(array $nodes)
    {
        // namespaces can be only top-level nodes, so we can scan them directly
        foreach ($nodes as $topLevelNode) {
            if ($topLevelNode instanceof Namespace_) {
                // file has namespace in it, nothing to change, returning null
                return null;
            }
        }

        // if we don't have a namespaces at all, this is global namespace, wrap everything in it
        $globalNamespaceNode = new Namespace_(new FullyQualified(''), $nodes);

        return [$globalNamespaceNode];
    }
}
