<?php
namespace Go\ParserReflection;

class ReflectionFunctionTest extends TestCaseBase
{
    const STUB_FILE55 = '/Stub/FileWithFunctions55.php';
    const STUB_FILE70 = '/Stub/FileWithFunctions70.php';

    /**
     * @var string
     */
    protected $lastFileSetUp;

    /**
     * @var ReflectionFile
     */
    protected $parsedRefFile;

    protected function setUpParsedRefFile($fileName)
    {
        if ($this->lastFileSetUp !== $fileName) {
            $reflectionFile = new ReflectionFile($fileName);
            $this->parsedRefFile = $reflectionFile;

            if (!preg_match('/\\bNeverIncluded\\b/', $fileName)) {
                include_once $fileName;
            }
            $this->lastFileSetUp = $fileName;
        }
    }

    protected function setUp()
    {
        $fileName = stream_resolve_include_path(__DIR__ . self::STUB_FILE55);
        $this->setUpParsedRefFile($fileName);
    }

    public function generalInfoGetterProvider()
    {
        $allNameGetters = [
            'getStartLine', 'getEndLine', 'getDocComment', 'getExtension', 'getExtensionName',
            'getName', 'getNamespaceName', 'getShortName', 'inNamespace', 'getStaticVariables',
            'getNumberOfParameters', 'getNumberOfRequiredParameters', '__toString', 'isDisabled',
            'returnsReference', 'getClosureScopeClass', 'getClosureThis', 'hasReturnType'
        ];

        $result = [];
        foreach ($allNameGetters as $getterName) {
            $result[] = ['getterName' => $getterName];
        }
        return $result;
    }

    /**
     * Provides a list of files for analysis
     *
     * @return array
     */
    public function fileProvider()
    {
        $files = ['PHP5.5' => ['fileName' => __DIR__ . '/Stub/FileWithFunctions55.php']];

        if (PHP_VERSION_ID >= 70000) {
            $files['PHP7.0'] = ['fileName' => __DIR__ . '/Stub/FileWithFunctions70.php'];
        }

        return $files;
    }

    /**
     * Provides a list of functions for analysis in the form [Function, FileName]
     *
     * @return array
     */
    public function functionProvider()
    {
        // Random selection of built in functions.
        $builtInFunctions = ['preg_match', 'date', 'create_function'];
        $functions = [];
        foreach ($builtInFunctions as $functionsName) {
            $functions[$functionsName] = [
                'function'     => $functionsName,
                'fileName'     => null,
                'origFunction' => $functionsName,
            ];
        }
        $files = $this->fileProvider();
        foreach ($files as $filenameArgList) {
            $argKeys = array_keys($filenameArgList);
            $fileName = $filenameArgList[$argKeys[0]];
            $resolvedFileName = stream_resolve_include_path($fileName);
            $fileNode = ReflectionEngine::parseFile($resolvedFileName);
            list($fakeFileName, $funcNameFilter) = $this->getNeverIncludedFileFilter($resolvedFileName);
            $realAndFake = [
                'real' => ['file' => $resolvedFileName, 'funcNameFilter' => 'strval'       ],
                'fake' => ['file' => $fakeFileName,     'funcNameFilter' => $funcNameFilter],
            ];

            $reflectionFile = new ReflectionFile($resolvedFileName, $fileNode);
            foreach ($reflectionFile->getFileNamespaces() as $fileNamespace) {
                foreach ($fileNamespace->getFunctions() as $parsedFunction) {
                    foreach ($realAndFake as $funcFaker) {
                        $funcNameFilter = $funcFaker['funcNameFilter'];
                        if (
                            ($funcNameFilter === 'strval') ||
                            ($funcNameFilter($parsedFunction->getName()) != $parsedFunction->getName())
                        ) {
                            $functions[$argKeys[0] . ': ' . $funcNameFilter($parsedFunction->getName())] = [
                                'function'     => $funcNameFilter($parsedFunction->getName()),
                                'fileName'     => $funcFaker['file'],
                                'origFunction' => $parsedFunction->getName(),
                            ];
                        }
                    }
                }
            }
        }

        return $functions;
    }

    public function generalInfoGettersForFunctionsProvider()
    {
        $includedOnlyMethods = [
            'getClosureScopeClass',
            'getClosureThis',
        ];
        return 
            array_filter(
                $this->getPermutations(
                    $this->generalInfoGetterProvider(),
                    $this->functionProvider()),
                (function ($argList) use ($includedOnlyMethods) {
                    return
                        !in_array($argList['getterName'], $includedOnlyMethods) ||
                        !preg_match('/\\bNeverIncluded\\b/', $argList['function']);
                }));
    }


    /**
     * Performs method-by-method comparison with original reflection
     *
     * @dataProvider generalInfoGettersForFunctionsProvider
     *
     * @param string $getterName    Name of the reflection method to test.
     * @param string $functionName  Name of the function to test $getterName with.
     * @param string $fileName      Name of file containing $functionName.
     * @param string $origFunction  Name of included function $functionName is based on.
     */
    public function testGeneralInfoGetters($getterName, $functionName, $fileName, $origFunction)
    {
        $unsupportedGetters = [];
        if (PHP_VERSION_ID < 70000) {
            $unsupportedGetters = array_merge($unsupportedGetters, ['hasReturnType']);
        }
        if (in_array($getterName, $unsupportedGetters)) {
            $this->markTestSkipped("ReflectionFunction::{$getterName} not supported in " . PHP_VERSION);
        }
        $comparisonTransformer = 'strval';
        if (preg_match('/\\bNeverIncluded\\b/', $functionName)) {
            $comparisonTransformer = (function ($inStr) {
                return preg_replace(',([/\\\\])Stub\\b,', '\\1Stub\\1NeverIncluded', $inStr);
            });
        }
        if ($fileName) {
            $this->setUpParsedRefFile($fileName);
            $fileNamespace = $this->parsedRefFile->getFileNamespace(
                $this->getNamespaceFromName($functionName));
            $refFunction   = $fileNamespace->getFunction(
                $this->getShortNameFromName($functionName));
        } else {
            $this->lastFileSetUp = null;
            $this->parsedRefFile = null;
            $refFunction         = new ReflectionFunction($functionName);
        }
        $originalRefFunction = new \ReflectionFunction($origFunction);
        $expectedValue       = $originalRefFunction->$getterName();
        $actualValue         = $refFunction->$getterName();
        $this->assertReflectorValueSame(
            $expectedValue,
            $actualValue,
            "{$getterName}() for function {$functionName} should be equal",
            $comparisonTransformer
        );
    }

    public function testCoverAllMethods()
    {
        $allInternalMethods = get_class_methods(\ReflectionFunction::class);
        $allMissedMethods   = [];

        foreach ($allInternalMethods as $internalMethodName) {
            if ('export' === $internalMethodName) {
                continue;
            }
            $refMethod    = new \ReflectionMethod(ReflectionFunction::class, $internalMethodName);
            $definerClass = $refMethod->getDeclaringClass()->getName();
            if (strpos($definerClass, 'Go\\ParserReflection') !== 0) {
                $allMissedMethods[] = $internalMethodName;
            }
        }

        if ($allMissedMethods) {
            $this->markTestIncomplete('Methods ' . join($allMissedMethods, ', ') . ' are not implemented');
        }
    }

    public function testGetClosureMethod()
    {
        $fileNamespace = $this->parsedRefFile->getFileNamespace('Go\ParserReflection\Stub');
        $refFunc       = $fileNamespace->getFunction('noGeneratorFunc');
        $closure       = $refFunc->getClosure();

        $this->assertInstanceOf(\Closure::class, $closure);
        $retValue = $closure();
        $this->assertEquals(100, $retValue);
    }

    public function testInvokeMethod()
    {
        $fileNamespace = $this->parsedRefFile->getFileNamespace('Go\ParserReflection\Stub');
        $refFunc       = $fileNamespace->getFunction('funcWithReturnArgs');
        $retValue      = $refFunc->invoke(1, 2, 3);
        $this->assertEquals([1, 2, 3], $retValue);
    }

    public function testInvokeArgsMethod()
    {
        $fileNamespace = $this->parsedRefFile->getFileNamespace('Go\ParserReflection\Stub');
        $refFunc       = $fileNamespace->getFunction('funcWithReturnArgs');
        $retValue      = $refFunc->invokeArgs([1, 2, 3]);
        $this->assertEquals([1, 2, 3], $retValue);
    }

    public function testGetReturnTypeMethod()
    {
        if (PHP_VERSION_ID < 70000) {
            $this->markTestSkipped('Test available only for PHP7.0 and newer');
        }

        $fileName = stream_resolve_include_path(__DIR__ . self::STUB_FILE70);

        $reflectionFile = new ReflectionFile($fileName);
        include_once $fileName;

        foreach ($reflectionFile->getFileNamespaces() as $fileNamespace) {
            foreach ($fileNamespace->getFunctions() as $refFunction) {
                $functionName        = $refFunction->getName();
                $originalRefFunction = new \ReflectionFunction($functionName);
                $hasReturnType       = $refFunction->hasReturnType();
                $this->assertSame(
                    $originalRefFunction->hasReturnType(),
                    $hasReturnType,
                    "Presence of return type for function {$functionName} should be equal"
                );
                if ($hasReturnType) {
                    $parsedReturnType   = $refFunction->getReturnType();
                    $originalReturnType = $originalRefFunction->getReturnType();
                    $this->assertSame($originalReturnType->allowsNull(), $parsedReturnType->allowsNull());
                    $this->assertSame($originalReturnType->isBuiltin(), $parsedReturnType->isBuiltin());
                    $this->assertSame($originalReturnType->__toString(), $parsedReturnType->__toString());
                } else {
                    $this->assertSame(
                        $originalRefFunction->getReturnType(),
                        $refFunction->getReturnType()
                    );
                }
            }
        }
    }
}
