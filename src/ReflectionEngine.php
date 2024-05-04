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
use Go\ParserReflection\NodeVisitor\RootNamespaceNormalizer;
use InvalidArgumentException;
use PhpParser\Lexer;
use PhpParser\Node;
use PhpParser\Node\Stmt\ClassConst;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Property;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\Parser;
use PhpParser\ParserFactory;

/**
 * AST-based reflection engine, powered by PHP-Parser
 */
class ReflectionEngine
{
    protected static ?LocatorInterface $locator;

    /**
     * @var Node[][]
     */
    protected static array $parsedFiles = [];

    protected static ?int $maximumCachedFiles;

    protected static Parser $parser;

    protected static NodeTraverser $traverser;

    private function __construct() {}

    public static function init(LocatorInterface $locator): void
    {
        self::$parser = (new ParserFactory())->createForHostVersion();

        self::$traverser = $traverser = new NodeTraverser();
        $traverser->addVisitor(new NameResolver(
            null,
            [
                'preserveOriginalNames' => true,
                'replaceNodes' => false,
            ]
        ));
        $traverser->addVisitor(new RootNamespaceNormalizer());

        self::$locator = $locator;
    }

    /**
     * Limits number of files, that can be cached at any given moment
     */
    public static function setMaximumCachedFiles(int $newLimit): void
    {
        self::$maximumCachedFiles = $newLimit;
        if (count(self::$parsedFiles) > $newLimit) {
            self::$parsedFiles = array_slice(self::$parsedFiles, 0, $newLimit);
        }
    }

    /**
     * Locates a file name for class
     */
    public static function locateClassFile(string $fullClassName): string
    {
        if (class_exists($fullClassName, false)
            || interface_exists($fullClassName, false)
            || trait_exists($fullClassName, false)
        ) {
            $refClass      = new \ReflectionClass($fullClassName);
            $classFileName = $refClass->getFileName();
        } else {
            $classFileName = self::$locator->locateClass($fullClassName);
        }

        if (!$classFileName) {
            throw new InvalidArgumentException("Class $fullClassName was not found by locator");
        }

        return $classFileName;
    }

    /**
     * Tries to parse a class by name using LocatorInterface
     */
    public static function parseClass(string $fullClassName): ClassLike
    {
        $classFileName  = self::locateClassFile($fullClassName);
        $namespaceParts = explode('\\', $fullClassName);
        $className      = array_pop($namespaceParts);
        $namespaceName  = implode('\\', $namespaceParts);

        // we have a namespace node somewhere
        $namespace      = self::parseFileNamespace($classFileName, $namespaceName);
        $namespaceNodes = $namespace->stmts;

        $namespaceNode = self::findClassLikeNodeByClassName($namespaceNodes, $className);
        if ($namespaceNode instanceof ClassLike) {
            $namespaceNode->setAttribute('fileName', $classFileName);

            return $namespaceNode;
        }

        throw new InvalidArgumentException("Class $fullClassName was not found in the $classFileName");
    }

    /**
     * Loop through an array and find a ClassLike statement by the given class name.
     *
     * If an if statement like `if (false) {` is found, the class will also be search inside that if statement.
     * This relies on the guide of greg0ire on how to deprecate a type.
     *
     * @see https://dev.to/greg0ire/how-to-deprecate-a-type-in-php-48cf
     */
    protected static function findClassLikeNodeByClassName(array $nodes, string $className): ?ClassLike
    {
        foreach ($nodes as $node) {
            if ($node instanceof ClassLike && $node->name->toString() == $className) {
                return $node;
            }
            if ($node instanceof Node\Stmt\If_
                && $node->cond instanceof Node\Expr\ConstFetch
                && isset($node->cond->name->parts[0])
                && $node->cond->name->parts[0] === 'false'
            ) {
                $result = self::findClassLikeNodeByClassName($node->stmts, $className);

                if ($result instanceof ClassLike) {
                    return $result;
                }
            }
        }

        return null;
    }

    /**
     * Parses class method
     */
    public static function parseClassMethod(string $fullClassName, string $methodName): ClassMethod
    {
        $class      = self::parseClass($fullClassName);
        $classNodes = $class->stmts;

        foreach ($classNodes as $classLevelNode) {
            if ($classLevelNode instanceof ClassMethod && $classLevelNode->name->toString() === $methodName) {
                return $classLevelNode;
            }
        }

        throw new InvalidArgumentException("Method $methodName was not found in the $fullClassName");
    }

    /**
     * Parses class property
     *
     * @return array Pair of [Property and PropertyItem] nodes
     */
    public static function parseClassProperty(string $fullClassName, string $propertyName): array
    {
        $class      = self::parseClass($fullClassName);
        $classNodes = $class->stmts;

        foreach ($classNodes as $classLevelNode) {
            if ($classLevelNode instanceof Property) {
                foreach ($classLevelNode->props as $classProperty) {
                    if ($classProperty->name->toString() === $propertyName) {
                        return [$classLevelNode, $classProperty];
                    }
                }
            }
        }

        throw new InvalidArgumentException("Property $propertyName was not found in the $fullClassName");
    }

    /**
     * Parses class constants
     *
     * @return array Pair of [ClassConst and Const_] nodes
     */
    public static function parseClassConstant(string $fullClassName, string $constantName): array
    {
        $class      = self::parseClass($fullClassName);
        $classNodes = $class->stmts;

        foreach ($classNodes as $classLevelNode) {
            if ($classLevelNode instanceof ClassConst) {
                foreach ($classLevelNode->consts as $classConst) {
                    if ($classConst->name->toString() === $constantName) {
                        return [$classLevelNode, $classConst];
                    }
                }
            }
        }

        throw new InvalidArgumentException("ClassConstant $constantName was not found in the $fullClassName");
    }

    /**
     * Parses a file and returns an AST for it
     *
     * @param string|null $fileContent Optional content of the file
     *
     * @return Node[]
     */
    public static function parseFile(string $fileName, ?string $fileContent = null): array
    {
        $fileName = PathResolver::realpath($fileName);
        if (isset(self::$parsedFiles[$fileName]) && !isset($fileContent)) {
            return self::$parsedFiles[$fileName];
        }

        if (isset(self::$maximumCachedFiles) && (count(self::$parsedFiles) === self::$maximumCachedFiles)) {
            array_shift(self::$parsedFiles);
        }

        if (!isset($fileContent)) {
            $fileContent = file_get_contents($fileName);
        }
        $treeNodes = self::$parser->parse($fileContent);
        $treeNodes = self::$traverser->traverse($treeNodes);

        self::$parsedFiles[$fileName] = $treeNodes;

        return $treeNodes;
    }

    /**
     * Parses a file namespace and returns an AST for it
     *
     * @throws ReflectionException
     */
    public static function parseFileNamespace(string $fileName, string $namespaceName): Namespace_
    {
        $topLevelNodes = self::parseFile($fileName);
        // namespaces can be only top-level nodes, so we can scan them directly
        foreach ($topLevelNodes as $topLevelNode) {
            if (!$topLevelNode instanceof Namespace_) {
                continue;
            }
            $topLevelNodeName = $topLevelNode->name ? $topLevelNode->name->toString() : '';
            if (ltrim($topLevelNodeName, '\\') === trim($namespaceName, '\\')) {
                return $topLevelNode;
            }
        }

        throw new ReflectionException("Namespace $namespaceName was not found in the file $fileName");
    }

    public static function getParser(): Parser
    {
        return self::$parser;
    }
}
