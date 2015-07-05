<?php
/**
 * Created by PhpStorm.
 * User: 1
 * Date: 22.03.2015
 * Time: 12:12
 */

namespace ParserReflection;


use PhpParser\Node;
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

    public function __construct(array $topLevelNodes, $fileName)
    {
        $this->topLevelNodes = $topLevelNodes;
        $this->fileName      = $fileName;
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
    public function getNamespaces()
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
    public function hasNamespace($namespaceName)
    {
        $namespaces = $this->getNamespaces();

        return isset($namespaces[$namespaceName]);
    }

    /**
     * Returns a namespace from the file or false if no such a namespace
     *
     * @param string $namespaceName
     *
     * @return bool|ReflectionFileNamespace
     */
    public function getNamespace($namespaceName)
    {
        if ($this->hasNamespace($namespaceName)) {
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
                $namespaces[$topLevelNode->name->toString()] = new ReflectionFileNamespace($topLevelNode);
            }
        }

        return $namespaces;
    }
}