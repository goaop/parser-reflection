<?php
/**
 * Created by PhpStorm.
 * User: 1
 * Date: 22.03.2015
 * Time: 12:12
 */

namespace ParserReflection;


use PhpParser\Node;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Stmt\Namespace_;

class ReflectionFile implements \Reflector
{

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
     * @var Node
     */
    private $topLevelNodes;

    public function __construct($fileName, $topLevelNodes = null)
    {
        $this->fileName      = $fileName;
        $this->topLevelNodes = $topLevelNodes ?: ReflectionEngine::parseFile($fileName);
    }

    public function getName()
    {
        return $this->fileName;
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
     * Returns the presence of namespace in the file
     *
     * @param string $namespaceName
     *
     * @return bool
     */
    public function hasFileNamespace($namespaceName)
    {
        $namespaces = $this->getFileNamespaces();

        return isset($namespaces[$namespaceName]);
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
     * (PHP 5)<br/>
     * Exports
     * @link http://php.net/manual/en/reflector.export.php
     * @return string
     */
    public static function export()
    {
        // TODO: Implement export() method.
    }

    /**
     * (PHP 5)<br/>
     * To string
     * @link http://php.net/manual/en/reflector.tostring.php
     * @return string
     */
    public function __toString()
    {
        // TODO: Implement __toString() method.
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
                $namespaceName = $topLevelNode->name->toString();

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