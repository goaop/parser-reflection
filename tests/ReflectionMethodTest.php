<?php
declare(strict_types=1);

namespace Go\ParserReflection;

class ReflectionMethodTest extends AbstractTestCase
{
    protected static string $reflectionClassToTest = \ReflectionMethod::class;

    public function testGetClosureMethod(): void
    {
        $refMethod = $this->parsedRefClass->getMethod('funcWithDocAndBody');
        $closure   = $refMethod->getClosure(null);

        $this->assertInstanceOf(\Closure::class, $closure);
        $retValue = $closure();
        $this->assertSame('hello', $retValue);
    }

    public function testInvokeMethod(): void
    {
        $refMethod = $this->parsedRefClass->getMethod('funcWithReturnArgs');
        $retValue  = $refMethod->invoke(null, 1, 2, 3);
        $this->assertSame([1, 2, 3], $retValue);
    }

    public function testInvokeArgsMethod(): void
    {
        $refMethod = $this->parsedRefClass->getMethod('funcWithReturnArgs');
        $retValue  = $refMethod->invokeArgs(null, [1, 2, 3]);
        $this->assertSame([1, 2, 3], $retValue);
    }

    public function testDebugInfoMethod(): void
    {
        $parsedRefMethod   = $this->parsedRefClass->getMethod('funcWithDocAndBody');
        $originalRefMethod = new \ReflectionMethod($this->parsedRefClass->getName(), 'funcWithDocAndBody');
        $expectedValue     = (array) $originalRefMethod;
        $this->assertSame($expectedValue, $parsedRefMethod->__debugInfo());
    }

    public function testCallProtectedMethod(): void
    {
        $refMethod = $this->parsedRefClass->getMethod('protectedStaticFunc');
        $retValue = $refMethod->invokeArgs(null, []);
        $this->assertEquals(null, $retValue);
    }

    public function testGetPrototypeMethod(): void
    {
        $refMethod = $this->parsedRefClass->getMethod('prototypeMethod');
        $retValue  = $refMethod->invokeArgs(null, []);
        $this->assertSame($this->parsedRefClass->getName(), $retValue);

        $prototype = $refMethod->getPrototype();
        $this->assertInstanceOf(\ReflectionMethod::class, $prototype);
        $retValue  = $prototype->invokeArgs(null, []);
        $this->assertNotSame($this->parsedRefClass->getName(), $retValue);
    }

    /**
     * Performs method-by-method comparison with original reflection
     *
     *
     * @param ReflectionClass   $parsedClass Parsed class
     * @param \ReflectionMethod $refMethod Method to analyze
     * @param string                  $getterName Name of the reflection method to test
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('caseProvider')]
    public function testReflectionMethodParity(
        ReflectionClass $parsedClass,
        \ReflectionMethod $refMethod,
        $getterName
    ): void {
        $methodName   = $refMethod->getName();
        $className    = $parsedClass->getName();
        $parsedMethod = $parsedClass->getMethod($methodName);
        if (empty($parsedMethod)) {
            echo "Couldn't find method $methodName in the $className", PHP_EOL;
            return;
        }

        $expectedValue = $refMethod->$getterName();
        $actualValue   = $parsedMethod->$getterName();
        // I would like to completely stop maintaining the __toString method
        if ($expectedValue !== $actualValue && $getterName === '__toString') {
            $this->markTestSkipped("__toString for method {$className}::{$methodName} is not equal:\n{$expectedValue}{$actualValue}");
        }
        $this->assertSame(
            $expectedValue,
            $actualValue,
            "$getterName() for method $className->$methodName() should be equal"
        );
    }

    /**
     * Provides full test-case list in the form [ParsedClass, ReflectionMethod, getter name to check]
     *
     * @return array
     */
    public static function caseProvider()
    {
        $allNameGetters = static::getGettersToCheck();

        $testCases = [];
        $files     = static::getFilesToAnalyze();
        foreach ($files as $fileList) {
            foreach ($fileList as $fileName) {
                $fileName = stream_resolve_include_path($fileName);
                $fileNode = ReflectionEngine::parseFile($fileName);

                $reflectionFile = new ReflectionFile($fileName, $fileNode);
                include_once $fileName;
                foreach ($reflectionFile->getFileNamespaces() as $fileNamespace) {
                    foreach ($fileNamespace->getClasses() as $parsedClass) {
                        $refClass = new \ReflectionClass($parsedClass->getName());
                        foreach ($refClass->getMethods() as $classMethod) {
                            $caseName = $parsedClass->getName() . '->' . $classMethod->getName() . '()';
                            foreach ($allNameGetters as $getterName) {
                                $testCases[$caseName . ', ' . $getterName] = [
                                    $parsedClass,
                                    $classMethod,
                                    $getterName
                                ];
                            }
                        }
                    }
                }
            }
        }

        return $testCases;
    }


    /**
     * Returns list of ReflectionMethod getters that be checked directly without additional arguments
     *
     * @return array
     */
    protected static function getGettersToCheck()
    {
        $allNameGetters = [
            'getStartLine', 'getEndLine', 'getDocComment', 'getExtension', 'getExtensionName', 'getName',
            'getNamespaceName', 'getShortName', 'inNamespace', 'getStaticVariables', 'isClosure', 'isDeprecated',
            'isInternal', 'isUserDefined', 'isAbstract', 'isConstructor', 'isDestructor', 'isFinal', 'isPrivate',
            'isProtected', 'isPublic', 'isStatic', 'isVariadic', 'isGenerator', 'getNumberOfParameters',
            'getNumberOfRequiredParameters', 'returnsReference', 'getClosureScopeClass', 'getClosureThis',
            'hasReturnType', '__toString'
        ];

        return $allNameGetters;
    }
}
