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
use PhpParser\Node;
use PhpParser\Node\Stmt\Namespace_;

/**
 * AST-based reflector for the source file
 * @see \Go\ParserReflection\ReflectionFileTest
 */
class ReflectionFile
{

    /**
     * Name of the file for reflection
     */
    protected string $fileName;

    /**
     * List of namespaces in the file
     *
     * @var ReflectionFileNamespace[]
     */
    protected array $fileNamespaces;

    /**
     * Top-level nodes for the file
     *
     * @var Node[]
     */
    private array $topLevelNodes;

    /**
     * ReflectionFile constructor.
     *
     * @param null|Node[] $topLevelNodes Optional corresponding list of AST nodes for that file
     */
    public function __construct(string $fileName, ?array $topLevelNodes = null)
    {
        $fileName            = PathResolver::realpath($fileName);
        $this->fileName      = $fileName;
        $this->topLevelNodes = $topLevelNodes ?: ReflectionEngine::parseFile($fileName);
    }

    /**
     * Returns a namespace from the file or false if no such a namespace
     *
     * @throws ReflectionException If namespace doesn't exists in the file
     */
    public function getFileNamespace(string $namespaceName): ReflectionFileNamespace
    {
        if ($this->hasFileNamespace($namespaceName)) {
            return $this->fileNamespaces[$namespaceName];
        }

        throw new ReflectionException("Could not find the namespace " . $namespaceName . " in the file " . $this->fileName);
    }

    /**
     * Gets the list of namespaces in the file
     *
     * @return ReflectionFileNamespace[]
     */
    public function getFileNamespaces(): array
    {
        if (!isset($this->fileNamespaces)) {
            $this->fileNamespaces = $this->findFileNamespaces();
        }

        return $this->fileNamespaces;
    }

    /**
     * Returns the name of current reflected file
     */
    public function getName(): string
    {
        return $this->fileName;
    }

    /**
     * Returns an AST-nodes for file
     *
     * @return Node[]
     */
    public function getNodes(): array
    {
        return $this->topLevelNodes;
    }

    /**
     * Returns the presence of namespace in the file
     */
    public function hasFileNamespace(string $namespaceName): bool
    {
        $namespaces = $this->getFileNamespaces();

        return isset($namespaces[$namespaceName]);
    }

    /**
     * Checks if the current file is in strict mode
     */
    public function isStrictMode(): bool
    {
        // declare statement for the strict_types can be only top-level node
        $topLevelNode = reset($this->topLevelNodes);
        if (!$topLevelNode instanceof Node\Stmt\Declare_) {
            return false;
        }

        $declareStatement = reset($topLevelNode->declares);
        $isStrictTypeKey  = $declareStatement->key->toString() === 'strict_types';
        $isScalarValue    = $declareStatement->value instanceof Node\Scalar\Int_;
        $isStrictMode     = $isStrictTypeKey && $isScalarValue && $declareStatement->value->value === 1;

        return $isStrictMode;
    }

    /**
     * Searches for file namespaces in the given AST
     *
     * @return ReflectionFileNamespace[]
     */
    private function findFileNamespaces(): array
    {
        $namespaces = [];

        // namespaces can be only top-level nodes, so we can scan them directly
        foreach ($this->topLevelNodes as $topLevelNode) {
            if ($topLevelNode instanceof Namespace_) {
                $namespaceName = $topLevelNode->name ? $topLevelNode->name->toString() : '';

                $namespaces[$namespaceName] = new ReflectionFileNamespace(
                    $this->fileName,
                    $namespaceName,
                    $topLevelNode
                );
            }
        }

        return $namespaces;
    }
}
