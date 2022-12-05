<?php
/**
 * Parser Reflection API
 *
 * @copyright Copyright 2016-2022, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 *
 * @noinspection PhpUnhandledExceptionInspection
 * @noinspection PhpMissingFieldTypeInspection
 */
declare(strict_types=1);

namespace Go\ParserReflection;

use PHPUnit\Framework\TestCase;
use Go\ParserReflection\Stub\AbstractClassWithMethods;
use Reflection as BaseReflection;
use ReflectionMethod as BaseReflectionMethod;

abstract class AbstractTestCase extends TestCase
{
    const DEFAULT_STUB_FILENAME = '/Stub/FileWithClasses55.php';

    /**
     * @var ReflectionFileNamespace
     */
    protected ReflectionFileNamespace $parsedRefFileNamespace;

    /**
     * @var ReflectionClass
     */
    protected $parsedRefClass;

    /**
     * Name of the class to compare
     *
     * @var string
     */
    protected static string $reflectionClassToTest = BaseReflection::class;

    /**
     * Name of the class to load for default tests
     *
     * @var string
     */
    protected static string $defaultClassToLoad = AbstractClassWithMethods::class;

    public function testCoverAllMethods()
    {
        $allInternalMethods = get_class_methods(static::$reflectionClassToTest);
        $allMissedMethods   = [];

        foreach ($allInternalMethods as $internalMethodName) {
            if ('export' === $internalMethodName) {
                continue;
            }
            $refMethod    = new BaseReflectionMethod(__NAMESPACE__ . '\\' . static::$reflectionClassToTest, $internalMethodName);
            $definerClass = $refMethod->getDeclaringClass()->getName();
            if (!str_starts_with($definerClass, __NAMESPACE__)) {
                $allMissedMethods[] = $internalMethodName;
            }
        }

        if ($allMissedMethods) {
            $this->markTestIncomplete('Methods ' . join(', ', $allMissedMethods) . ' are not implemented');
        } else {
            $this->assertTrue(true);
        }
    }


    /**
     * Provides a list of files for analysis
     *
     * @return array
     */
    public function getFilesToAnalyze(): array
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
        if (PHP_VERSION_ID >= 80000) {
            $files['PHP8.0'] = [
                __DIR__ . '/Stub/FileWithClasses80.php',
                __DIR__ . '/Stub/FileWithAttributes80.php',
            ];
        }

        return $files;
    }

    /**
     * Returns list of ReflectionMethod getters that be checked directly without additional arguments
     *
     * @return array
     */
    abstract protected function getGettersToCheck(): array;

    /**
     * Setups file for parsing
     *
     * @param string $fileName File to use
     */
    protected function setUpFile(string $fileName)
    {
        $fileName = stream_resolve_include_path($fileName);
        $fileNode = ReflectionEngine::parseFile($fileName);

        $reflectionFile = new ReflectionFile($fileName, $fileNode);

        $parsedFileNamespace          = $reflectionFile->getFileNamespace('Go\ParserReflection\Stub');
        $this->parsedRefFileNamespace = $parsedFileNamespace;
        $this->parsedRefClass         = $parsedFileNamespace->getClass(static::$defaultClassToLoad);

        include_once $fileName;
    }

    protected function setUp(): void
    {
        $this->setUpFile(__DIR__ . self::DEFAULT_STUB_FILENAME);
    }
}
