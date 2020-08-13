<?php
/**
 * Parser Reflection API
 *
 * @copyright Copyright 2016, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */
namespace Go\ParserReflection;

use Go\ParserReflection\Stub\AbstractClassWithMethods;

abstract class AbstractTestCase extends \PHPUnit_Framework_TestCase
{
    const DEFAULT_STUB_FILENAME = '/Stub/FileWithClasses55.php';

    /**
     * @var ReflectionFileNamespace
     */
    protected $parsedRefFileNamespace;

    /**
     * @var ReflectionClass
     */
    protected $parsedRefClass;

    /**
     * Name of the class to compare
     *
     * @var string
     */
    protected static $reflectionClassToTest = \Reflection::class;

    /**
     * Name of the class to load for default tests
     *
     * @var string
     */
    protected static $defaultClassToLoad = AbstractClassWithMethods::class;

    public function testCoverAllMethods()
    {
        $allInternalMethods = get_class_methods(static::$reflectionClassToTest);
        $allMissedMethods   = [];

        foreach ($allInternalMethods as $internalMethodName) {
            if ('export' === $internalMethodName) {
                continue;
            }
            $refMethod    = new \ReflectionMethod(__NAMESPACE__ . '\\' . static::$reflectionClassToTest, $internalMethodName);
            $definerClass = $refMethod->getDeclaringClass()->getName();
            if (strpos($definerClass, __NAMESPACE__) !== 0) {
                $allMissedMethods[] = $internalMethodName;
            }
        }

        if ($allMissedMethods) {
            $this->markTestIncomplete('Methods ' . join(', ', $allMissedMethods) . ' are not implemented');
        }
    }


    /**
     * Provides a list of files for analysis
     *
     * @return array
     */
    public function getFilesToAnalyze()
    {
        $files = ['PHP5.5' => [__DIR__ . '/Stub/FileWithClasses55.php']];

        if (PHP_VERSION_ID >= 50600) {
            $files['PHP5.6'] = [__DIR__ . '/Stub/FileWithClasses56.php'];
        }
        if (PHP_VERSION_ID >= 70000) {
            $files['PHP7.0'] = [__DIR__ . '/Stub/FileWithClasses70.php'];
        }
        if (PHP_VERSION_ID >= 70100) {
            $files['PHP7.1'] = [__DIR__ . '/Stub/FileWithClasses71.php'];
        }

        return $files;
    }

    /**
     * Returns list of ReflectionMethod getters that be checked directly without additional arguments
     *
     * @return array
     */
    abstract protected function getGettersToCheck();

    /**
     * Setups file for parsing
     *
     * @param string $fileName File to use
     */
    protected function setUpFile($fileName)
    {
        $fileName = stream_resolve_include_path($fileName);
        $fileNode = ReflectionEngine::parseFile($fileName);

        $reflectionFile = new ReflectionFile($fileName, $fileNode);

        $parsedFileNamespace          = $reflectionFile->getFileNamespace('Go\ParserReflection\Stub');
        $this->parsedRefFileNamespace = $parsedFileNamespace;
        $this->parsedRefClass         = $parsedFileNamespace->getClass(static::$defaultClassToLoad);

        include_once $fileName;
    }

    protected function setUp()
    {
        $this->setUpFile(__DIR__ . self::DEFAULT_STUB_FILENAME);
    }
}
