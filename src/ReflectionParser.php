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
class ReflectionParser
{
    /**
     * @var null|LocatorInterface
     */
    protected $locator = null;

    /**
     * @var array|Node[]
     */
    protected $parsedFiles = array();

    /**
     * @var null|integer
     */
    protected $maximumCachedFiles;

    /**
     * @var null|Parser
     */
    protected $parser = null;

    /**
     * @var null|NodeTraverser
     */
    protected $traverser = null;


    public function __construct(LocatorInterface $locator)
    {
        $refParser   = new \ReflectionClass(Parser::class);
        $isNewParser = $refParser->isInterface();
        if (!$isNewParser) {
            $this->parser = new Parser(new Lexer(['usedAttributes' => [
                'comments', 'startLine', 'endLine', 'startTokenPos', 'endTokenPos', 'startFilePos', 'endFilePos'
            ]]));
        } else {
            $this->parser = (new ParserFactory)->create(ParserFactory::PREFER_PHP7);
        }

        $this->traverser = $traverser = new NodeTraverser();
        $traverser->addVisitor(new NameResolver());
        $traverser->addVisitor(new RootNamespaceNormalizer());

        $this->locator = $locator;
    }

    /**
     * Limits number of files, that can be cached at any given moment
     *
     * @param integer $newLimit New limit
     *
     * @return void
     */
    public function setMaximumCachedFiles($newLimit)
    {
        $this->maximumCachedFiles = $newLimit;
        if (count($this->parsedFiles) > $newLimit) {
            $this->parsedFiles = array_slice($this->parsedFiles, 0, $newLimit);
        }
    }

    /**
     * Return class reflection object
     *
     * @param string $fullName Full name of the class
     *
     * @return void
     */
    public function getClassReflection($fullName)
    {
        if (class_exists($fullName, false)
            || interface_exists($fullName, false)
            || trait_exists($fullName, false)
        ) {
            return new \ReflectionClass($fullName);
        }
        return new ReflectionClass($fullName, null, $this);
    }

    /**
     * Locates a file name for class
     *
     * @param string $fullClassName Full name of the class
     *
     * @return string
     */
    public function locateClassFile($fullClassName)
    {
        if (class_exists($fullClassName, false)
            || interface_exists($fullClassName, false)
            || trait_exists($fullClassName, false)
        ) {
            $refClass      = new \ReflectionClass($fullClassName);
            $classFileName = $refClass->getFileName();
        } else {
            $classFileName = $this->locator->locateClass($fullClassName);
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
    public function parseClass($fullClassName)
    {
        $classFileName  = $this->locateClassFile($fullClassName);
        $namespaceParts = explode('\\', $fullClassName);
        $className      = array_pop($namespaceParts);
        $namespaceName  = join('\\', $namespaceParts);

        // we have a namespace node somewhere
        $namespace      = $this->parseFileNamespace($classFileName, $namespaceName);
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
    public function parseClassMethod($fullClassName, $methodName)
    {
        $class      = $this->parseClass($fullClassName);
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
    public function parseClassProperty($fullClassName, $propertyName)
    {
        $class      = $this->parseClass($fullClassName);
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
    public function parseFile($fileName, $fileContent = null)
    {
        $fileName = PathResolver::realpath($fileName);
        if (isset($this->parsedFiles[$fileName])) {
            return $this->parsedFiles[$fileName];
        }

        if (isset($this->maximumCachedFiles) && (count($this->parsedFiles) === $this->maximumCachedFiles)) {
            array_shift($this->parsedFiles);
        }

        if (!isset($fileContent)) {
            $fileContent = file_get_contents($fileName);
        }
        $treeNode = $this->parser->parse($fileContent);
        $treeNode = $this->traverser->traverse($treeNode);

        $this->parsedFiles[$fileName] = $treeNode;

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
    public function parseFileNamespace($fileName, $namespaceName)
    {
        $topLevelNodes = $this->parseFile($fileName);
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

    /**
     * Provides back compatibility with static ReflectionEngine
     * @return void
     */
    public function initStaticEngine(&$parsedFiles, &$maximumCachedFiles, &$parser, &$traverser)
    {
        $parsedFiles = $this->parsedFiles;
        $maximumCachedFiles = $this->maximumCachedFiles;
        $parser = $this->parser;
        $traverser = $this->traverser;
        $this->parsedFiles = &$parsedFiles;
        $this->maximumCachedFiles = &$maximumCachedFiles;
        $this->parser = &$parser;
        $this->traverser = &$traverser;
    }

}
