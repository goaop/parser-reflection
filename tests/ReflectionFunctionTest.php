<?php
namespace Go\ParserReflection;

class ReflectionFunctionTest extends TestCaseBase
{
    const STUB_FILE55 = '/Stub/FileWithFunctions55.php';
    const STUB_FILE70 = '/Stub/FileWithFunctions70.php';

    /**
     * @var ReflectionFile
     */
    protected $parsedRefFile;

    protected function setUpParsedRefFile()
    {
        $fileName = stream_resolve_include_path(__DIR__ . self::STUB_FILE55);

        $reflectionFile = new ReflectionFile($fileName);
        $this->parsedRefFile = $reflectionFile;

        include_once $fileName;
    }

    protected function setUp()
    {
        $this->setUpParsedRefFile();
    }

    public function getGeneralInfoGetters()
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

    public function getFunctionsToTest()
    {
        $this->setUpParsedRefFile();
        $result = [];
        foreach ($this->parsedRefFile->getFileNamespaces() as $fileNamespace) {
            foreach ($fileNamespace->getFunctions() as $refFunction) {
                $result[] = ['functionName' => $refFunction->getName()];
            }
        }
        return $result;
    }

    public function getGeneralInfoGettersForFunctions()
    {
        return $this->getPermutations(
            $this->getGeneralInfoGetters(),
            $this->getFunctionsToTest());
    }


    /**
     * Performs method-by-method comparison with original reflection
     *
     * @dataProvider getGeneralInfoGettersForFunctions
     *
     * @param string $getterName    Name of the reflection method to test.
     * @param string $functionName  Name of the function to test $getterName with.
     */
    public function testGeneralInfoGetters($getterName, $functionName)
    {
        $unsupportedGetters = [];
        if (PHP_VERSION_ID < 70000) {
            $unsupportedGetters = array_merge($unsupportedGetters, ['hasReturnType']);
        }
        if (in_array($getterName, $unsupportedGetters)) {
            $this->markTestSkipped("ReflectionFunction::{$getterName} not supported in " . PHP_VERSION);
        }
        $namespaceParts      = explode('\\', $functionName);
        $funcShortName       = array_pop($namespaceParts);
        $namespace           = implode('\\', $namespaceParts);
        $fileNamespace       = $this->parsedRefFile->getFileNamespace($namespace);
        $refFunction         = $fileNamespace->getFunction($funcShortName);
        $originalRefFunction = new \ReflectionFunction($functionName);
        $expectedValue       = $originalRefFunction->$getterName();
        $actualValue         = $refFunction->$getterName();
        $this->assertSame(
            $expectedValue,
            $actualValue,
            "{$getterName}() for function {$functionName} should be equal"
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
        include $fileName;

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
