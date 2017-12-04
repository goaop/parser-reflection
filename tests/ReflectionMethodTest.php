<?php
namespace Go\ParserReflection;

class ReflectionMethodTest extends AbstractClassTestCaseBase
{
    protected static $reflectionClassToTest = \ReflectionMethod::class;

    public function testGetClosureMethod()
    {
        $refMethod = $this->parsedRefClass->getMethod('funcWithDocAndBody');
        $closure   = $refMethod->getClosure(null);

        $this->assertInstanceOf(\Closure::class, $closure);
        $retValue = $closure();
        $this->assertEquals('hello', $retValue);
    }

    public function testInvokeMethod()
    {
        $refMethod = $this->parsedRefClass->getMethod('funcWithReturnArgs');
        $retValue  = $refMethod->invoke(null, 1, 2, 3);
        $this->assertEquals([1, 2, 3], $retValue);
    }

    public function testInvokeArgsMethod()
    {
        $refMethod = $this->parsedRefClass->getMethod('funcWithReturnArgs');
        $retValue  = $refMethod->invokeArgs(null, [1, 2, 3]);
        $this->assertEquals([1, 2, 3], $retValue);
    }

    public function testDebugInfoMethod()
    {
        $parsedRefMethod   = $this->parsedRefClass->getMethod('funcWithDocAndBody');
        $originalRefMethod = new \ReflectionMethod($this->parsedRefClass->getName(), 'funcWithDocAndBody');
        $expectedValue     = (array) $originalRefMethod;
        $this->assertSame($expectedValue, $parsedRefMethod->___debugInfo());
    }

    public function testSetAccessibleMethod()
    {
        $refMethod = $this->parsedRefClass->getMethod('protectedStaticFunc');
        $refMethod->setAccessible(true);
        $retValue = $refMethod->invokeArgs(null, []);
        $this->assertEquals(null, $retValue);
    }

    public function testGetPrototypeMethod()
    {
        $refMethod = $this->parsedRefClass->getMethod('prototypeMethod');
        $retValue  = $refMethod->invokeArgs(null, []);
        $this->assertEquals($this->parsedRefClass->getName(), $retValue);

        $prototype = $refMethod->getPrototype();
        $this->assertInstanceOf(\ReflectionMethod::class, $prototype);
        $prototype->setAccessible(true);
        $retValue  = $prototype->invokeArgs(null, []);
        $this->assertNotEquals($this->parsedRefClass->getName(), $retValue);
    }

    /**
     * Performs method-by-method comparison with original reflection
     *
     * @dataProvider caseProvider
     *
     * @param ReflectionClass    $parsedClass  Parsed class.
     * @param \ReflectionMethod  $refMethod    Method to analyze.
     * @param string             $getterName   Name of the reflection method to test.
     */
    public function testReflectionMethodParity(
        ReflectionClass $parsedClass,
        \ReflectionMethod $refMethod,
        $getterName
    ) {
        $methodName            = $refMethod->getName();
        $className             = $parsedClass->getName();
        $parsedMethod          = $parsedClass->getMethod($methodName);
        $comparisonTransformer = 'strval';
        if (preg_match('/\\bNeverIncluded\\b/', $className)) {
            $this->setUpFakeFileLocator();
            $comparisonTransformer = (function ($inStr) {
                return preg_replace(',([/\\\\])Stub\\b,', '\\1Stub\\1NeverIncluded', $inStr);
            });
        }
        if (empty($parsedMethod)) {
            echo "Couldn't find method $methodName in the $className", PHP_EOL;
            return;
        }

        $expectedValue = $refMethod->$getterName();
        $actualValue   = $parsedMethod->$getterName();
        $this->assertReflectorValueSame(
            $expectedValue,
            $actualValue,
            get_class($parsedMethod) . "->$getterName() for method $className->$methodName() should be equal\nexpected: " . $this->getStringificationOf($expectedValue) . "\nactual: " . $this->getStringificationOf($actualValue),
            $comparisonTransformer
        );
    }

    /**
     * Provides full test-case list in the form [ParsedClass, ReflectionMethod, getter name to check]
     *
     * @return array
     */
    public function caseProvider()
    {
        $allNameGetters = $this->getGettersToCheck();
        $includedOnlyMethods = [
            'getClosureScopeClass',
            'getClosureThis',
        ];

        $testCases = [];
        $classes   = $this->getClassesToAnalyze();
        foreach ($classes as $testCaseDesc => $classFilePair) {
            if ($classFilePair['fileName']) {
                $fileNode       = ReflectionEngine::parseFile($classFilePair['fileName']);
                $reflectionFile = new ReflectionFile($classFilePair['fileName'], $fileNode);
                $namespace      = $this->getNamespaceFromName($classFilePair['class']);
                $fileNamespace  = $reflectionFile->getFileNamespace($namespace);
                $parsedClass    = $fileNamespace->getClass($classFilePair['class']);
                if ($classFilePair['class'] === $classFilePair['origClass']) {
                    include_once $classFilePair['fileName'];
                }
            } else {
                $parsedClass    = new ReflectionClass($classFilePair['class']);
            }
            $refClass = new \ReflectionClass($classFilePair['origClass']);
            foreach ($refClass->getMethods() as $classMethod) {
                $caseName = $testCaseDesc . '->' . $classMethod->getName() . '()';
                foreach ($allNameGetters as $getterName) {
                    if (
                        ($classFilePair['class'] === $classFilePair['origClass']) ||
                        !in_array($getterName, $includedOnlyMethods)
                    ) {
                        $testCases[$caseName . ', ' . $getterName] = [
                            'parsedClass' => $parsedClass,
                            'refMethod'   => $classMethod,
                            'getterName'  => $getterName,
                        ];
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
    protected function getGettersToCheck()
    {
        $allNameGetters = [
            'getStartLine', 'getEndLine', 'getDocComment', 'getExtension', 'getExtensionName', 'getName',
            'getNamespaceName', 'getShortName', 'inNamespace', 'getStaticVariables', 'isClosure', 'isDeprecated',
            'isInternal', 'isUserDefined', 'isAbstract', 'isConstructor', 'isDestructor', 'isFinal', 'isPrivate',
            'isProtected', 'isPublic', 'isStatic', '__toString', 'getNumberOfParameters',
            'getNumberOfRequiredParameters', 'returnsReference', 'getClosureScopeClass', 'getClosureThis'
        ];

        if (PHP_VERSION_ID >= 50600) {
            $allNameGetters[] = 'isVariadic';
            $allNameGetters[] = 'isGenerator';
        }

        if (PHP_VERSION_ID >= 70000) {
            $allNameGetters[] = 'hasReturnType';
        }

        return $allNameGetters;
    }
}
