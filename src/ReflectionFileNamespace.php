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

namespace Go\ParserReflection;

use Go\ParserReflection\Instrument\PathResolver;
use Go\ParserReflection\Resolver\NodeExpressionResolver;
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
 * @see \Go\ParserReflection\ReflectionFileNamespaceTest
 */
class ReflectionFileNamespace
{
    /**
     * List of classes in the namespace
     *
     * @var ReflectionClass[]
     */
    protected array $fileClasses;

    /**
     * List of functions in the namespace
     *
     * @var ReflectionFunction[]
     */
    protected array $fileFunctions;

    /**
     * Map of constants in the namespace
     */
    protected array $fileConstants;

    /**
     * Map of constants in the namespace including defined via "define(...)"
     */
    protected array $fileConstantsWithDefined;

    /**
     * List of imported namespaces (aliases)
     */
    protected array $fileNamespaceAliases;

    /**
     * Namespace node
     */
    private Namespace_ $namespaceNode;

    /**
     * Name of the file
     */
    private string $fileName;

    /**
     * File namespace constructor
     *
     * @param Namespace_|null $namespaceNode Optional AST-node for this namespace block
     */
    public function __construct(string $fileName, string $namespaceName, ?Namespace_ $namespaceNode = null)
    {
        $fileName = PathResolver::realpath($fileName);
        if (!isset($namespaceNode)) {
            $namespaceNode = ReflectionEngine::parseFileNamespace($fileName, $namespaceName);
        }
        $this->namespaceNode = $namespaceNode;
        $this->fileName      = $fileName;
    }

    /**
     * Returns the concrete class from the file namespace
     *
     * @throws ReflectionException if there is no such class
     */
    public function getClass(string $className): ReflectionClass
    {
        if (!$this->hasClass($className)) {
            throw new ReflectionException("Could not find the class " . $className . " in the file " . $this->fileName);
        }

        return $this->fileClasses[$className];
    }

    /**
     * Gets list of classes in the namespace
     *
     * @return ReflectionClass[]
     */
    public function getClasses(): array
    {
        if (!isset($this->fileClasses)) {
            $this->fileClasses = $this->findClasses();
        }

        return $this->fileClasses;
    }

    /**
     * Returns a value for the given constant
     *
     * @throws ReflectionException if there is no such constant in the file's namespace
     */
    public function getConstant(string $constantName): mixed
    {
        if (!$this->hasConstant($constantName)) {
            throw new ReflectionException("Could not find the constant " . $constantName . " in the file " . $this->fileName);
        }

        return $this->fileConstants[$constantName];
    }

    /**
     * Returns a list of defined constants in the namespace
     *
     * @param bool $withDefined Include constants defined via "define(...)" in results.
     */
    public function getConstants(bool $withDefined = false): array
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
     * Gets doc comments from a namespace node if it exists, otherwise "false"
     */
    public function getDocComment(): string|false
    {
        $docComment = false;
        $comments   = $this->namespaceNode->getAttribute('comments');

        if ($comments) {
            $docComment = (string)$comments[0];
        }

        return $docComment;
    }

    /**
     * Gets starting line number or false if information is not available
     */
    public function getEndLine(): int|false
    {
        if ($this->namespaceNode->hasAttribute('endLine')) {
            return $this->namespaceNode->getAttribute('endLine');
        }

        return false;
    }

    /**
     * Returns the name of file
     */
    public function getFileName(): string
    {
        return $this->fileName;
    }

    /**
     * Returns the concrete function from the file namespace
     *
     * @throws ReflectionException if there is no function
     */
    public function getFunction(string $functionName): ReflectionFunction
    {
        if (!$this->hasFunction($functionName)) {
            throw new ReflectionException("Could not find the function " . $functionName . " in the file " . $this->fileName);
        }

        return $this->fileFunctions[$functionName];
    }

    /**
     * Gets list of functions in the namespace
     *
     * @return ReflectionFunction[]
     */
    public function getFunctions(): array
    {
        if (!isset($this->fileFunctions)) {
            $this->fileFunctions = $this->findFunctions();
        }

        return $this->fileFunctions;
    }

    /**
     * Gets namespace name
     */
    public function getName(): string
    {
        $nameNode = $this->namespaceNode->name;

        return $nameNode ? $nameNode->toString() : '';
    }

    /**
     * Returns a list of namespace aliases
     */
    public function getNamespaceAliases(): array
    {
        if (!isset($this->fileNamespaceAliases)) {
            $this->fileNamespaceAliases = $this->findNamespaceAliases();
        }

        return $this->fileNamespaceAliases;
    }

    /**
     * Returns an AST-node for namespace
     */
    public function getNode(): Namespace_
    {
        return $this->namespaceNode;
    }

    /**
     * Helper method to access last token position for namespace
     *
     * This method is useful because namespace can be declared with braces or without them
     */
    public function getLastTokenPosition(): int
    {
        $endNamespaceTokenPosition = $this->namespaceNode->getAttribute('endTokenPos');

        /** @var Node $lastNamespaceNode */
        $lastNamespaceNode         = end($this->namespaceNode->stmts);
        $endStatementTokenPosition = $lastNamespaceNode->getAttribute('endTokenPos');

        return max($endNamespaceTokenPosition, $endStatementTokenPosition);
    }

    /**
     * Gets starting line number or false if information is not available
     */
    public function getStartLine(): int|false
    {
        if ($this->namespaceNode->hasAttribute('startLine')) {
            return $this->namespaceNode->getAttribute('startLine');
        }

        return false;
    }

    /**
     * Checks if the given class is present in this file namespace
     */
    public function hasClass(string $className): bool
    {
        $classes = $this->getClasses();

        return isset($classes[$className]);
    }

    /**
     * Checks if the given constant is present in this file namespace
     */
    public function hasConstant(string $constantName): bool
    {
        $constants = $this->getConstants();

        return isset($constants[$constantName]);
    }

    /**
     * Checks if the given function is present in this file namespace
     */
    public function hasFunction(string $functionName): bool
    {
        $functions = $this->getFunctions();

        return isset($functions[$functionName]);
    }

    /**
     * Searches for classes in the given AST
     *
     * @return ReflectionClass[]
     */
    private function findClasses(): array
    {
        $classes       = [];
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
     * @return ReflectionFunction[]
     */
    private function findFunctions(): array
    {
        $functions     = [];
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
     */
    private function findConstants(bool $withDefined = false): array
    {
        $constants        = [];
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
                    try {
                        $functionCallNode = $namespaceLevelNode->expr;
                        $expressionSolver->process($functionCallNode->args[0]->value);
                        $constantName = $expressionSolver->getValue();

                        $expressionSolver->process($functionCallNode->args[1]->value);
                        $constantValue = $expressionSolver->getValue();

                        $constants[$constantName] = $constantValue;
                    } catch (\Throwable) {
                        // Ignore all possible errors during evaluation of runtime constants defined in the code
                    }
                }
            }
        }

        return $constants;
    }

    /**
     * Searches for namespace aliases for the current block
     */
    private function findNamespaceAliases(): array
    {
        $namespaceAliases = [];

        // aliases can be only top-level nodes in the namespace, so we can scan them directly
        foreach ($this->namespaceNode->stmts as $namespaceLevelNode) {
            if ($namespaceLevelNode instanceof Use_) {
                $useAliases = $namespaceLevelNode->uses;
                if (!empty($useAliases)) {
                    foreach ($useAliases as $useNode) {
                        $namespaceAliases[$useNode->name->toString()] = $useNode->getAlias()->toString();
                    }
                }
            }
        }

        return $namespaceAliases;
    }
}
