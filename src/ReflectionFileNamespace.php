<?php
/**
 * Parser Reflection API
 *
 * @copyright Copyright 2015, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace ParserReflection;


use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\Namespace_;

/**
 * AST-based reflection for the concrete namespace in the file
 */
class ReflectionFileNamespace implements \Reflector
{
    protected $fileClasses;
    protected $fileFunctions;

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
        $nameNode = $this->namespaceNode->name;

        return $nameNode ? $nameNode->toString() : '';
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
            $this->fileClasses = $this->findClasses();
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
     * Gets list of functions in the namespace
     *
     * @return ReflectionFunction[]|array
     */
    public function getFunctions()
    {
        if (!isset($this->fileFunctions)) {
            $this->fileFunctions = $this->findFunctions();
        }

        return $this->fileFunctions;
    }

    /**
     * Checks if the given function is present in this filenamespace
     *
     * @param string $functionName
     *
     * @return bool
     */
    public function hasFunction($functionName)
    {
        $functions = $this->getFunctions();

        return isset($functions[$functionName]);
    }

    /**
     * Returns the concrete function from the file namespace or false if there is no function
     *
     * @param string $functionName
     *
     * @return bool|ReflectionFunction
     */
    public function getFunction($functionName)
    {
        if ($this->hasFunction($functionName)) {
            return $this->fileFunctions[$functionName];
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
        $classes       = array();
        $namespaceName = $this->getName();
        // classes can be only top-level nodes in the namespace, so we can scan them directly
        foreach ($this->namespaceNode->stmts as $namespaceLevelNode) {
            if ($namespaceLevelNode instanceof Class_) {
                $classShortName = $namespaceLevelNode->name;
                $className = $namespaceName ? $namespaceName .'\\' . $classShortName : $classShortName;

                $namespaceLevelNode->setAttribute('fileName', $this->fileName);
                $classes[$className] = new ReflectionClass($className, $namespaceLevelNode);
            }
        }

        return $classes;
    }

    /**
     * Searches for functions in the given AST
     *
     * @return array
     */
    private function findFunctions()
    {
        $functions     = array();
        $namespaceName = $this->getName();

        // functions can be only top-level nodes in the namespace, so we can scan them directly
        foreach ($this->namespaceNode->stmts as $namespaceLevelNode) {
            if ($namespaceLevelNode instanceof Function_) {
                $funcShortName = $namespaceLevelNode->name;
                $functionName  = $namespaceName ? $namespaceName .'\\' . $funcShortName : $funcShortName;

                $namespaceLevelNode->setAttribute('fileName', $this->fileName);
                $functions[$functionName] = new ReflectionFunction($functionName, $namespaceLevelNode);
            }
        }

        return $functions;
    }
}