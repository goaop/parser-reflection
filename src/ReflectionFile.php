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


use PhpParser\Node;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Stmt\Namespace_;

/**
 * AST-based reflector for the source file
 */
class ReflectionFile
{

    /**
     * Name of the file for reflectino
     *
     * @var string
     */
    protected $fileName;

    /**
     * List of namespaces in the file
     *
     * @var ReflectionFileNamespace[]|array
     */
    protected $fileNamespaces;

    /**
     * Top-level nodes for the file
     *
     * @var Node[]
     */
    private $topLevelNodes;

    /**
     * ReflectionFile constructor.
     *
     * @param string $fileName Name of the file to reflect
     * @param null|array|Node[] $topLevelNodes Optional corresponding list of AST nodes for that file
     */
    public function __construct($fileName, $topLevelNodes = null)
    {
        $this->fileName      = $fileName;
        $this->topLevelNodes = $topLevelNodes ?: ReflectionEngine::parseFile($fileName);
    }

    /**
     * Returns a namespace from the file or false if no such a namespace
     *
     * @param string $namespaceName
     *
     * @return bool|ReflectionFileNamespace
     */
    public function getFileNamespace($namespaceName)
    {
        if ($this->hasFileNamespace($namespaceName)) {
            return $this->fileNamespaces[$namespaceName];
        }

        return false;
    }

    /**
     * Gets the list of namespaces in the file
     *
     * @return array|ReflectionFileNamespace[]
     */
    public function getFileNamespaces()
    {
        if (!isset($this->fileNamespaces)) {
            $this->fileNamespaces = $this->findFileNamespaces();
        }

        return $this->fileNamespaces;
    }

    /**
     * Returns the name of current reflected file
     *
     * @return string
     */
    public function getName()
    {
        return $this->fileName;
    }

    /**
     * Returns the presence of namespace in the file
     *
     * @param string $namespaceName Namespace to check
     *
     * @return bool
     */
    public function hasFileNamespace($namespaceName)
    {
        $namespaces = $this->getFileNamespaces();

        return isset($namespaces[$namespaceName]);
    }

    /**
     * Searches for file namespaces in the given AST
     *
     * @return array|ReflectionFileNamespace[]
     */
    private function findFileNamespaces()
    {
        $namespaces = array();

        // namespaces can be only top-level nodes, so we can scan them directly
        foreach ($this->topLevelNodes as $topLevelNode) {
            if ($topLevelNode instanceof Namespace_) {
                $namespaceName = $topLevelNode->name ? $topLevelNode->name->toString() : '\\';

                $namespaces[$namespaceName] = new ReflectionFileNamespace(
                    $this->fileName,
                    $namespaceName,
                    $topLevelNode
                );
            }
        }

        if (!$namespaces) {
            // if we don't have a namespaces at all, this is global namespace
            $globalNamespaceNode = new Namespace_(new FullyQualified(''), $this->topLevelNodes);
            $namespaces['\\']    = new ReflectionFileNamespace($this->fileName, '\\', $globalNamespaceNode);
        }

        return $namespaces;
    }
}
