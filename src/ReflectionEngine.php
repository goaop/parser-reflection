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

use PhpParser\Lexer;
use PhpParser\Node;
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
     * @var null|Parser
     */
    protected static $parser = null;

    /**
     * @var null|NodeTraverser
     */
    protected static $traverser = null;

    private function __construct() {}

    public static function init(LocatorInterface $locator)
    {
        $refParser   = new \ReflectionClass(Parser::class);
        $isNewParser = $refParser->isInterface();
        if (!$isNewParser) {
            self::$parser = new Parser(new Lexer(['usedAttributes' => [
                'comments', 'startLine', 'endLine', 'startTokenPos', 'endTokenPos', 'startFilePos', 'endFilePos'
            ]]));
        } else {
            self::$parser = (new ParserFactory)->create(ParserFactory::PREFER_PHP7);
        }

        self::$traverser = $traverser = new NodeTraverser();
        $traverser->addVisitor(new NameResolver());

        self::$locator = $locator;
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

        if ($namespaceName) {
            // we have a namespace nodes somewhere
            $namespace      = self::parseFileNamespace($classFileName, $namespaceName);
            $namespaceNodes = $namespace->stmts;
        } else {
            // global namespace
            $namespaceNodes = self::parseFile($classFileName);
        }

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
            if ($classLevelNode instanceof ClassMethod && $classLevelNode->name == $methodName) {
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
                    if ($classProperty->name == $propertyName) {
                        return [$classLevelNode, $classProperty];
                    }
                }
            }
        }

        throw new \InvalidArgumentException("Property $propertyName was not found in the $fullClassName");
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
        if (isset(self::$parsedFiles[$fileName])) {
            return self::$parsedFiles[$fileName];
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
     */
    public static function parseFileNamespace($fileName, $namespaceName)
    {
        $topLevelNodes = self::parseFile($fileName);
        // namespaces can be only top-level nodes, so we can scan them directly
        foreach ($topLevelNodes as $topLevelNode) {
            if ($topLevelNode instanceof Namespace_ && ($topLevelNode->name->toString() == $namespaceName)) {
                return $topLevelNode;
            }
        }

        throw new ReflectionException("Namespace $namespaceName was not found in the file $fileName");
    }

}
