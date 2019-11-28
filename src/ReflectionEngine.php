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
use Go\ParserReflection\NodeVisitor\RootNamespaceNormalizer;
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
    /**
     * @var null|LocatorInterface
     */
    protected static $locator = null;

    /**
     * @var array|Node[]
     */
    protected static $parsedFiles = array();

    /**
     * @var null|integer
     */
    protected static $maximumCachedFiles;

    /**
     * @var null|Parser
     */
    protected static $parser = null;

    /**
     * @var null|NodeTraverser
     */
    protected static $traverser = null;

    /**
     * @var null|Lexer
     */
    protected static $lexer = null;

    private function __construct() {}

    public static function init(LocatorInterface $locator)
    {
        self::$lexer = new Lexer(['usedAttributes' => [
            'comments',
            'startLine',
            'endLine',
            'startTokenPos',
            'endTokenPos',
            'startFilePos',
            'endFilePos'
        ]]);

        self::$parser = (new ParserFactory)->create(ParserFactory::PREFER_PHP7, self::$lexer);

        self::$traverser = $traverser = new NodeTraverser();
        $traverser->addVisitor(new NameResolver());
        $traverser->addVisitor(new RootNamespaceNormalizer());

        self::$locator = $locator;
    }

    /**
     * Limits number of files, that can be cached at any given moment
     *
     * @param integer $newLimit New limit
     *
     * @return void
     */
    public static function setMaximumCachedFiles($newLimit)
    {
        self::$maximumCachedFiles = $newLimit;
        if (count(self::$parsedFiles) > $newLimit) {
            self::$parsedFiles = array_slice(self::$parsedFiles, 0, $newLimit);
        }
    }

    /**
     * Locates a file name for class
     *
     * @param string $fullClassName Full name of the class
     *
     * @return string
     */
    public static function locateClassFile($fullClassName)
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
            throw new \InvalidArgumentException("Class $fullClassName was not found by locator");
        }

        return $classFileName;
    }

    /**
     * Tries to parse a class by name using LocatorInterface
     *
     * @param string $fullClassName Class name to load
     *
     * @return ClassLike
     */
    public static function parseClass($fullClassName)
    {
        $classFileName  = self::locateClassFile($fullClassName);
        $namespaceParts = explode('\\', $fullClassName);
        $className      = array_pop($namespaceParts);
        $namespaceName  = join('\\', $namespaceParts);

        // we have a namespace node somewhere
        $namespace      = self::parseFileNamespace($classFileName, $namespaceName);
        $namespaceNodes = $namespace->stmts;

        foreach ($namespaceNodes as $namespaceLevelNode) {
            if ($namespaceLevelNode instanceof ClassLike && $namespaceLevelNode->name == $className) {
                $namespaceLevelNode->setAttribute('fileName', $classFileName);

                return $namespaceLevelNode;
            }
        }

        throw new \InvalidArgumentException("Class $fullClassName was not found in the $classFileName");
    }

    /**
     * Parses class method
     *
     * @param string $fullClassName Name of the class
     * @param string $methodName Name of the method
     *
     * @return ClassMethod
     */
    public static function parseClassMethod($fullClassName, $methodName)
    {
        $class      = self::parseClass($fullClassName);
        $classNodes = $class->stmts;

        foreach ($classNodes as $classLevelNode) {
            if ($classLevelNode instanceof ClassMethod && $classLevelNode->name->toString() == $methodName) {
                return $classLevelNode;
            }
        }

        throw new \InvalidArgumentException("Method $methodName was not found in the $fullClassName");
    }

    /**
     * Parses class property
     *
     * @param string $fullClassName Name of the class
     * @param string $propertyName Name of the property
     *
     * @return array Pair of [Property and PropertyProperty] nodes
     */
    public static function parseClassProperty($fullClassName, $propertyName)
    {
        $class      = self::parseClass($fullClassName);
        $classNodes = $class->stmts;

        foreach ($classNodes as $classLevelNode) {
            if ($classLevelNode instanceof Property) {
                foreach ($classLevelNode->props as $classProperty) {
                    if ($classProperty->name->toString() == $propertyName) {
                        return [$classLevelNode, $classProperty];
                    }
                }
            }
        }

        throw new \InvalidArgumentException("Property $propertyName was not found in the $fullClassName");
    }

    /**
     * Parses class constants
     *
     * @param string $fullClassName
     * @param string $constantName
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

        throw new \InvalidArgumentException("ClassConstant $constantName was not found in the $fullClassName");
    }

    /**
     * Parses a file and returns an AST for it
     *
     * @param string      $fileName Name of the file
     * @param string|null $fileContent Optional content of the file
     *
     * @return \PhpParser\Node[]
     */
    public static function parseFile($fileName, $fileContent = null)
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
        $treeNode = self::$parser->parse($fileContent);
        $treeNode = self::$traverser->traverse($treeNode);

        self::$parsedFiles[$fileName] = $treeNode;

        return $treeNode;
    }

    /**
     * Parses a file namespace and returns an AST for it
     *
     * @param string $fileName Name of the file
     * @param string $namespaceName Namespace name
     *
     * @return Namespace_
     * @throws ReflectionException
     */
    public static function parseFileNamespace($fileName, $namespaceName)
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

}
