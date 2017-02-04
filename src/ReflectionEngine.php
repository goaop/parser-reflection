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

/**
 * Static AST-based reflection engine, powered by PHP-Parser
 */
class ReflectionEngine
{
    /**
     * @var null|ReflectionParser
     */
    protected static $reflectionParser = null;

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

    private function __construct() {}

    public static function init(LocatorInterface $locator)
    {
        self::$locator = $locator;
        self::$reflectionParser = new ReflectionParser($locator);
        self::$reflectionParser->initStaticEngine(
            self::$parsedFiles,
            self::$maximumCachedFiles,
            self::$parser,
            self::$traverser
        );
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
        self::$reflectionParser->setMaximumCachedFiles($newLimit);
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
        return self::$reflectionParser->locateClassFile($fullClassName);
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
        return self::$reflectionParser->parseClass($fullClassName);
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
        return self::$reflectionParser->parseClassMethod($fullClassName, $methodName);
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
        return self::$reflectionParser->parseClassProperty($fullClassName, $propertyName);
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
        return self::$reflectionParser->parseFile($fileName, $fileContent = null);
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
        return self::$reflectionParser->parseFileNamespace($fileName, $namespaceName);
    }

}
