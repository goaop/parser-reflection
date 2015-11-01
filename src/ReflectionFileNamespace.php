<?php
/**
 * Created by PhpStorm.
 * User: 1
 * Date: 22.03.2015
 * Time: 12:12
 */

namespace ParserReflection;


use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Namespace_;

class ReflectionFileNamespace implements \Reflector
{
    protected $fileClasses;

    /**
     * Namespace node
     *
     * @var Namespace_
     */
    private $namespaceNode;

    /**
     * Name of the file
     *
     * @var string
     */
    private $fileName;

    public function __construct($fileName, $namespaceName, Namespace_ $namespaceNode = null)
    {
        if (!$namespaceNode) {
            $namespaceNode = ReflectionEngine::parseFileNamespace($fileName, $namespaceName);
        }
        $this->namespaceNode = $namespaceNode;
        $this->fileName      = $fileName;
    }

    /**
     * Gets namespace name
     *
     * @return string
     */
    public function getName()
    {
        return $this->namespaceNode->name->toString();
    }

    /**
     * Returns the name of file
     *
     * @return string
     */
    public function getFileName()
    {
        return $this->fileName;
    }

    /**
     * Returns the reflection of current file
     *
     * @return ReflectionFile
     */
    public function getFile()
    {
        return new ReflectionFile($this->fileName);
    }

    /**
     * Gets starting line number
     *
     * @return integer
     */
    public function getStartLine()
    {
        return $this->namespaceNode->getAttribute('startLine');
    }

    /**
     * Gets starting line number
     *
     * @return integer
     */
    public function getEndLine()
    {
        return $this->namespaceNode->getAttribute('endLine');
    }

    /**
     * Gets doc comments from a class.
     *
     * @return string|false The doc comment if it exists, otherwise "false"
     */
    public function getDocComment()
    {
        $docComment = false;
        $comments   = $this->namespaceNode->getAttribute('comments');

        if ($comments) {
            $docComment = (string) $comments[0];
        }

        return $docComment;
    }

    /**
     * Gets list of classes in the namespace
     *
     * @return ReflectionClass[]|array
     */
    public function getClasses()
    {
        if (!isset($this->fileClasses)) {
            $this->fileClasses = $this->findClasses($this->namespaceNode);
        }

        return $this->fileClasses;
    }

    /**
     * Checks if the given class is present in this filenamespace
     *
     * @param string $className
     *
     * @return bool
     */
    public function hasClass($className)
    {
        $classes = $this->getClasses();

        return isset($classes[$className]);
    }

    /**
     * Returns the concrete class from the file namespace or false if there is no class
     *
     * @param string $className
     *
     * @return bool|ReflectionClass
     */
    public function getClass($className)
    {
        if ($this->hasClass($className)) {
            return $this->fileClasses[$className];
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
     * Searches for classes in the given AST
     *
     * @return array|ReflectionClass[]
     */
    private function findClasses()
    {
        $classes = array();

        // classes can be only top-level nodes in the namespace, so we can scan them directly
        foreach ($this->namespaceNode->stmts as $namespaceLevelNode) {
            if ($namespaceLevelNode instanceof Class_) {
                $className = $namespaceLevelNode->name;

                $classes[$className] = new ReflectionClass($className, $namespaceLevelNode);
            }
        }

        return $classes;
    }
}