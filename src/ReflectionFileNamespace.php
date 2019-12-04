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

use Go\ParserReflection\Instrument\PathResolver;
use Go\ParserReflection\ValueResolver\NodeExpressionResolver;
use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Const_;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Use_;

/**
 * AST-based reflection for the concrete namespace in the file
 */
class ReflectionFileNamespace
{
    /**
     * List of classes in the namespace
     *
     * @var array|ReflectionClass[]
     */
    protected $fileClasses;

    /**
     * List of functions in the namespace
     *
     * @var array|ReflectionFunction[]
     */
    protected $fileFunctions;

    /**
     * List of constants in the namespace
     *
     * @var array
     */
    protected $fileConstants;

    /**
     * List of constants in the namespace including defined via "define(...)"
     *
     * @var array
     */
    protected $fileConstantsWithDefined;

    /**
     * List of imported namespaces (aliases)
     *
     * @var array
     */
    protected $fileNamespaceAliases;

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

    /**
     * File namespace constructor
     *
     * @param string          $fileName      Name of the file
     * @param string          $namespaceName Name of the namespace
     * @param Namespace_|null $namespaceNode Optional AST-node for this namespace block
     */
    public function __construct($fileName, $namespaceName, Namespace_ $namespaceNode = null)
    {
        if (!is_string($fileName)) {
            throw new \InvalidArgumentException(
                sprintf(
                    '$fileName must be a string, but a %s was passed',
                    gettype($fileName)
                )
            );
        }
        $fileName = PathResolver::realpath($fileName);
        if (!$namespaceNode) {
            $namespaceNode = ReflectionEngine::parseFileNamespace($fileName, $namespaceName);
        }
        $this->namespaceNode = $namespaceNode;
        $this->fileName      = $fileName;
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
     * Returns a value for the constant
     *
     * @param string $constantName name of the constant to fetch
     *
     * @return bool|mixed
     */
    public function getConstant($constantName)
    {
        if ($this->hasConstant($constantName)) {
            return $this->fileConstants[$constantName];
        }

        return false;
    }

    /**
     * Returns a list of defined constants in the namespace
     *
     * @param bool $withDefined Include constants defined via "define(...)" in results.
     *
     * @return array
     */
    public function getConstants($withDefined = false)
    {
        if ($withDefined) {
            if (!isset($this->fileConstantsWithDefined)) {
                $this->fileConstantsWithDefined = $this->findConstants(true);
            }

            return $this->fileConstantsWithDefined;
        }

        if (!isset($this->fileConstants)) {
            $this->fileConstants = $this->findConstants();
        }

        return $this->fileConstants;
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
            $docComment = (string)$comments[0];
        }

        return $docComment;
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
     * Returns the name of file
     *
     * @return string
     */
    public function getFileName()
    {
        return $this->fileName;
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
     * Returns a list of namespace aliases
     *
     * @return array
     */
    public function getNamespaceAliases()
    {
        if (!isset($this->fileNamespaceAliases)) {
            $this->fileNamespaceAliases = $this->findNamespaceAliases();
        }

        return $this->fileNamespaceAliases;
    }

    /**
     * Returns an AST-node for namespace
     *
     * @return Namespace_
     */
    public function getNode()
    {
        return $this->namespaceNode;
    }

    /**
     * Helper method to access last token position for namespace
     *
     * This method is useful because namespace can be declared with braces or without them
     */
    public function getLastTokenPosition()
    {
        $endNamespaceTokenPosition = $this->namespaceNode->getAttribute('endTokenPos');

        /** @var Node $lastNamespaceNode */
        $lastNamespaceNode         = end($this->namespaceNode->stmts);
        $endStatementTokenPosition = $lastNamespaceNode->getAttribute('endTokenPos');

        return max($endNamespaceTokenPosition, $endStatementTokenPosition);
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
     * Checks if the given constant is present in this filenamespace
     *
     * @param string $constantName
     *
     * @return bool
     */
    public function hasConstant($constantName)
    {
        $constants = $this->getConstants();

        return isset($constants[$constantName]);
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
            if ($namespaceLevelNode instanceof ClassLike) {
                $classShortName = $namespaceLevelNode->name->toString();
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
                $funcShortName = $namespaceLevelNode->name->toString();
                $functionName  = $namespaceName ? $namespaceName .'\\' . $funcShortName : $funcShortName;

                $namespaceLevelNode->setAttribute('fileName', $this->fileName);
                $functions[$funcShortName] = new ReflectionFunction($functionName, $namespaceLevelNode);
            }
        }

        return $functions;
    }

    /**
     * Searches for constants in the given AST
     *
     * @param bool $withDefined Include constants defined via "define(...)" in results.
     *
     * @return array
     */
    private function findConstants($withDefined = false)
    {
        $constants        = array();
        $expressionSolver = new NodeExpressionResolver($this);

        // constants can be only top-level nodes in the namespace, so we can scan them directly
        foreach ($this->namespaceNode->stmts as $namespaceLevelNode) {
            if ($namespaceLevelNode instanceof Const_) {
                $nodeConstants = $namespaceLevelNode->consts;
                if (!empty($nodeConstants)) {
                    foreach ($nodeConstants as $nodeConstant) {
                        $expressionSolver->process($nodeConstant->value);
                        $constants[$nodeConstant->name->toString()] = $expressionSolver->getValue();
                    }
                }
            }
        }

        if ($withDefined) {
            foreach ($this->namespaceNode->stmts as $namespaceLevelNode) {
                if ($namespaceLevelNode instanceof Expression
                    && $namespaceLevelNode->expr instanceof FuncCall
                    && $namespaceLevelNode->expr->name instanceof Name
                    && (string)$namespaceLevelNode->expr->name === 'define'
                ) {
                    $functionCallNode = $namespaceLevelNode->expr;
                    $expressionSolver->process($functionCallNode->args[0]->value);
                    $constantName = $expressionSolver->getValue();

                    // Ignore constants, for which name can't be determined.
                    if (strlen($constantName)) {
                        $expressionSolver->process($functionCallNode->args[1]->value);
                        $constantValue = $expressionSolver->getValue();

                        $constants[$constantName] = $constantValue;
                    }
                }
            }
        }

        return $constants;
    }

    /**
     * Searchse for namespace aliases for the current block
     *
     * @return array
     */
    private function findNamespaceAliases()
    {
        $namespaceAliases = [];

        // aliases can be only top-level nodes in the namespace, so we can scan them directly
        foreach ($this->namespaceNode->stmts as $namespaceLevelNode) {
            if ($namespaceLevelNode instanceof Use_) {
                $useAliases = $namespaceLevelNode->uses;
                if (!empty($useAliases)) {
                    foreach ($useAliases as $useNode) {
                        $namespaceAliases[$useNode->name->toString()] = (string) $useNode->getAlias();
                    }
                }
            }
        }

        return $namespaceAliases;
    }
}
