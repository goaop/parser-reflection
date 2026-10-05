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
use PhpParser\Node\Stmt\Enum_;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Property;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use PhpParser\PhpVersion;

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

    /**
     * Parser of the sources, created on the first parse
     */
    protected static ?Parser $parser = null;

    /**
     * Traverser resolving the names of the parsed sources, created on the first parse
     */
    protected static ?NodeTraverser $traverser = null;

    /**
     * Grammar version of the parser, null for the newest grammar supported by PHP-Parser
     */
    private static ?PhpVersion $phpVersion = null;

    private function __construct() {}

    /**
     * Initializes the engine with the given locator and an optional grammar version
     *
     * By default the newest grammar supported by PHP-Parser is used, so that sources written for a newer
     * PHP version than the host one can still be analysed statically.
     *
     * The parser is created on the first parse: the composer bootstrap of this package initializes the engine on
     * every request of an application, and most of them never parse anything.
     *
     * @param PhpVersion|null $phpVersion Optional PHP version of the grammar to parse sources with
     */
    public static function init(LocatorInterface $locator, ?PhpVersion $phpVersion = null): void
    {
        self::$locator    = $locator;
        self::$phpVersion = $phpVersion;
        self::$parser     = null;
        self::$traverser  = null;
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
            if (self::$locator === null) {
                throw new \LogicException('ReflectionEngine locator is not initialized. Call ReflectionEngine::init() first.');
            }
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
     * Tries to parse an enum by name using LocatorInterface
     *
     * @throws InvalidArgumentException if the class is not an enum
     */
    public static function parseEnum(string $fullClassName): Enum_
    {
        $node = self::parseClass($fullClassName);
        if (!$node instanceof Enum_) {
            throw new InvalidArgumentException("Class $fullClassName is not an enum");
        }

        return $node;
    }

    /**
     * Loop through an array and find a ClassLike statement by the given class name.
     *
     * If an if statement like `if (false) {` is found, the class will also be search inside that if statement.
     * This relies on the guide of greg0ire on how to deprecate a type.
     *
     * @see https://dev.to/greg0ire/how-to-deprecate-a-type-in-php-48cf
     */
    /**
     * @param Node[] $nodes
     */
    protected static function findClassLikeNodeByClassName(array $nodes, string $className): ?ClassLike
    {
        foreach ($nodes as $node) {
            if ($node instanceof ClassLike && $node->name !== null && $node->name->toString() == $className) {
                return $node;
            }
            if ($node instanceof Node\Stmt\If_
                && $node->cond instanceof Node\Expr\ConstFetch
                && $node->cond->name->toString() === 'false'
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
     * @return array{0: \PhpParser\Node\Stmt\Property, 1: \PhpParser\Node\PropertyItem} Pair of [Property and PropertyItem] nodes
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
     * @return array{0: \PhpParser\Node\Stmt\ClassConst|\PhpParser\Node\Stmt\EnumCase, 1: \PhpParser\Node\Const_|\PhpParser\Node\Stmt\EnumCase} Pair of [ClassConst and Const_] nodes
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
            // Enum cases are also reported as constants
            if ($classLevelNode instanceof \PhpParser\Node\Stmt\EnumCase
                && $classLevelNode->name->toString() === $constantName
            ) {
                return [$classLevelNode, $classLevelNode];
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
        $resolvedFileName = PathResolver::realpath($fileName);
        $fileName = is_string($resolvedFileName) ? $resolvedFileName : $fileName;
        if (isset(self::$parsedFiles[$fileName]) && !isset($fileContent)) {
            return self::$parsedFiles[$fileName];
        }

        if (isset(self::$maximumCachedFiles) && (count(self::$parsedFiles) === self::$maximumCachedFiles)) {
            array_shift(self::$parsedFiles);
        }

        if (!isset($fileContent)) {
            $fileContent = file_get_contents($fileName);
            if ($fileContent === false) {
                throw new ReflectionException("Could not read file: $fileName");
            }
        }
        $treeNodes = self::getParser()->parse($fileContent) ?? [];
        $treeNodes = self::getTraverser()->traverse($treeNodes);

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
        if (self::$parser === null) {
            $parserFactory = new ParserFactory();
            self::$parser  = isset(self::$phpVersion)
                ? $parserFactory->createForVersion(self::$phpVersion)
                : $parserFactory->createForNewestSupportedVersion();
        }

        return self::$parser;
    }

    /**
     * Returns the traverser resolving the names of the parsed sources
     */
    private static function getTraverser(): NodeTraverser
    {
        if (self::$traverser === null) {
            self::$traverser = new NodeTraverser();
            self::$traverser->addVisitor(new NameResolver(
                null,
                [
                    'preserveOriginalNames' => true,
                    'replaceNodes' => false,
                ]
            ));
            self::$traverser->addVisitor(new RootNamespaceNormalizer());
        }

        return self::$traverser;
    }
}
