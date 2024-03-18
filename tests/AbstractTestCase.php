<?php
declare(strict_types=1);
/**
 * Parser Reflection API
 *
 * @copyright Copyright 2016, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */
namespace Go\ParserReflection;

use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\TestCase;
use Go\ParserReflection\Stub\SimplePhp50AbstractClassWithMethods;

abstract class AbstractTestCase extends TestCase
{
    protected const DEFAULT_STUB_FILENAME = '/Stub/FileWithClasses55.php';

    protected ReflectionFile $parsedRefFile;

    protected ReflectionFileNamespace $parsedRefFileNamespace;

    protected ?ReflectionClass $parsedRefClass = null;

    /**
     * Name of the class to compare
     */
    protected static string $reflectionClassToTest = \Reflection::class;

    /**
     * Name of the class to load for default tests
     */
    protected static string $defaultClassToLoad = SimplePhp50AbstractClassWithMethods::class;

    #[DoesNotPerformAssertions]
    final public function testCoverAllMethods(): void
    {
        $allInternalMethods = get_class_methods(static::$reflectionClassToTest);
        $allMissedMethods   = [];

        foreach ($allInternalMethods as $internalMethodName) {
            $refMethod    = new \ReflectionMethod(__NAMESPACE__ . '\\' . static::$reflectionClassToTest, $internalMethodName);
            $definerClass = $refMethod->getDeclaringClass()->getName();
            if (!str_starts_with($definerClass, __NAMESPACE__)) {
                $allMissedMethods[] = $internalMethodName;
            }
        }

        if (count($allMissedMethods) > 0) {
            $this->markTestIncomplete('Methods ' . join(', ', $allMissedMethods) . ' are not implemented for ' . static::$reflectionClassToTest);
        }
    }

    /**
     * Provides a list of files for analysis
     */
    public static function getFilesToAnalyze(): \Generator
    {
        yield 'PHP5.5' => [
            __DIR__ . '/Stub/FileWithClasses55.php',
            __DIR__ . '/Stub/FileWithFunctions55.php',
            __DIR__ . '/Stub/FileWithParameters55.php',
        ];
        yield 'PHP5.6' => [
            __DIR__ . '/Stub/FileWithClasses56.php',
            __DIR__ . '/Stub/FileWithParameters56.php'
        ];
        yield 'PHP7.0' => [
            __DIR__ . '/Stub/FileWithClasses70.php',
            __DIR__ . '/Stub/FileWithParameters70.php',
            __DIR__ . '/Stub/FileWithFunctions70.php',
        ];
        yield 'PHP7.1' => [__DIR__ . '/Stub/FileWithClasses71.php'];
        yield 'PHP7.2' => [__DIR__ . '/Stub/FileWithClasses72.php'];
        yield 'PHP7.4' => [__DIR__ . '/Stub/FileWithClasses74.php'];
        yield 'PHP8.0' => [
            __DIR__ . '/Stub/FileWithClasses80.php',
            __DIR__ . '/Stub/FileWithParameters80.php',
            __DIR__ . '/Stub/FileWithFunction80.php',
        ];
        yield 'PHP8.1' => [__DIR__ . '/Stub/FileWithClasses81.php'];
        yield 'PHP8.2' => [__DIR__ . '/Stub/FileWithClasses82.php'];
        if (PHP_VERSION_ID >= 80300) {
            yield 'PHP8.3' => [__DIR__ . '/Stub/FileWithClasses83.php'];
        }
    }

    /**
     * Provides generator list in the form [ParsedFile, ParsedFileNamespace]
     */
    public static function getFileNamespacesToAnalyze(): \Generator
    {
        foreach (static::getFilesToAnalyze() as $prefix => $fileList) {
            foreach ($fileList as $fileName) {
                $fileName = stream_resolve_include_path($fileName);
                $fileNode = ReflectionEngine::parseFile($fileName);

                $reflectionFile = new ReflectionFile($fileName, $fileNode);
                include_once $fileName;
                foreach ($reflectionFile->getFileNamespaces() as $fileNamespace) {
                    yield $prefix => [$reflectionFile, $fileNamespace];
                }
            }
        }
    }

    /**
     * Provides generator list in the form [ParsedClass, ReflectionClass]
     */
    public static function classesDataProvider(): \Generator
    {
        foreach (static::getFileNamespacesToAnalyze() as $prefix => [$reflectionFile, $fileNamespace]) {
            foreach ($fileNamespace->getClasses() as $parsedClass) {
                $refClass = new \ReflectionClass($parsedClass->getName());
                yield $prefix . ' ' . $refClass->getName() => [$parsedClass, $refClass];
            }
        }
    }

    /**
     * Provides full test-case list in the form [ParsedClass, ReflectionMethod, \ReflectionMethod to check]
     */
    public static function methodsDataProvider(): \Generator
    {
        foreach (self::classesDataProvider() as $prefix => [$parsedClass, $refClass]) {
            foreach ($refClass->getMethods() as $classMethod) {
                $parsedMethod   = $parsedClass->getMethod($classMethod->getName());
                $fullMethodName = $parsedClass->getName() . '->' . $classMethod->getName() . '()';
                yield $prefix . ' ' . $fullMethodName => [
                    $parsedClass,
                    $parsedMethod,
                    $classMethod
                ];
            }
        }
    }

    /**
     * Provides generator list in the form [ParsedFunction, ReflectionFunction]
     */
    public static function functionsDataProvider(): \Generator
    {
        foreach (static::getFileNamespacesToAnalyze() as $prefix => [$reflectionFile, $fileNamespace]) {
            foreach ($fileNamespace->getFunctions() as $parsedFunction) {
                $refFunction = new \ReflectionFunction($parsedFunction->getName());
                yield $prefix . ' ' . $refFunction ->getName() => [$parsedFunction, $refFunction];
            }
        }
    }

    /**
     * Returns list of ReflectionMethod getters that be checked directly without additional arguments
     */
    abstract static protected function getGettersToCheck(): array;

    /**
     * Setups file for parsing
     */
    protected function setUpFile(string $fileName): void
    {
        $fileName = stream_resolve_include_path($fileName);
        $fileNode = ReflectionEngine::parseFile($fileName);

        $reflectionFile      = new ReflectionFile($fileName, $fileNode);
        $this->parsedRefFile = $reflectionFile;

        $parsedFileNamespace          = $reflectionFile->getFileNamespace('Go\ParserReflection\Stub');
        $this->parsedRefFileNamespace = $parsedFileNamespace;
        if ($parsedFileNamespace->hasClass(static::$defaultClassToLoad)) {
            $this->parsedRefClass = $parsedFileNamespace->getClass(static::$defaultClassToLoad);
        }

        include_once $fileName;
    }

    protected function setUp(): void
    {
        $this->setUpFile(__DIR__ . static::DEFAULT_STUB_FILENAME);
    }
}
