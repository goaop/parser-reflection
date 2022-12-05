<?php
/**
 * Parser Reflection API
 *
 * @copyright Copyright 2015-2022, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */
declare(strict_types=1);

namespace Go\ParserReflection;

use Go\ParserReflection\Instrument\PathResolver;
use PhpParser\Node;
use PhpParser\Node\Stmt\Namespace_;
use ReflectionException as BaseReflectionException;

/**
 * AST-based reflector for the source file
 */
class ReflectionFile
{
    /**
     * Name of the file for reflection
     *
     * @var string
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
     * @param string      $fileName      Name of the file to reflect
     * @param Node[]|null $topLevelNodes Optional corresponding list of AST nodes for that file
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
     * @param string $namespaceName
     *
     * @return bool|ReflectionFileNamespace
     */
    public function getFileNamespace(string $namespaceName): ReflectionFileNamespace|bool
    {
        if ($this->hasFileNamespace($namespaceName)) {
            return $this->fileNamespaces[$namespaceName];
        }

        return false;
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
     *
     * @param string $namespaceName
     *
     * @return bool
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
        $isScalarValue    = $declareStatement->value instanceof Node\Scalar\LNumber;
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

                try {
                    $namespaces[$namespaceName] = new ReflectionFileNamespace(
                        $this->fileName,
                        $namespaceName,
                        $topLevelNode
                    );
                } catch (BaseReflectionException) {
                    // Could not be reflected, so we skip it
                }
            }
        }

        return $namespaces;
    }
}
