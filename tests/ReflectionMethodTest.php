<?php
namespace Go\ParserReflection;

class ReflectionMethodTest extends \PHPUnit_Framework_TestCase
{
    const STUB_CLASS = 'Go\ParserReflection\Stub\AbstractClassWithMethods';

    /**
     * @var \ReflectionClass
     */
    protected $originalRefClass;

    /**
     * @var ReflectionClass
     */
    protected $parsedRefClass;

    protected function setUp()
    {
        $this->originalRefClass = $refClass = new \ReflectionClass(self::STUB_CLASS);

        $fileName       = $refClass->getFileName();
        $reflectionFile = new ReflectionFile($fileName);

        $parsedClass = $reflectionFile->getFileNamespace($refClass->getNamespaceName())->getClass($refClass->getName());
        $this->parsedRefClass = $parsedClass;
    }

    public function testGeneralInfoGetters()
    {
        $allNameGetters = [
            'getStartLine', 'getEndLine', 'getDocComment', 'getExtension', 'getExtensionName',
            'getName', 'getNamespaceName', 'getShortName', 'inNamespace', 'getStaticVariables',
            'isClosure', 'isDeprecated', 'isInternal', 'isUserDefined',
            'isAbstract', 'isConstructor', 'isDestructor', 'isFinal',
            'isPrivate', 'isProtected', 'isPublic', 'isStatic', '__toString',
            'getNumberOfParameters', 'getNumberOfRequiredParameters', 'returnsReference', 'getClosureScopeClass',
            'getClosureThis'
        ];

        if (PHP_VERSION_ID >= 50600) {
            $allNameGetters[] = 'isVariadic';
            $allNameGetters[] = 'isGenerator';
        }

        if (PHP_VERSION_ID >= 70000) {
            $allNameGetters[] = 'hasReturnType';
        }

        $allMethods = $this->originalRefClass->getMethods();

        foreach ($allMethods as $refMethod) {
            $methodName   = $refMethod->getName();
            $parsedMethod = $this->parsedRefClass->getMethod($methodName);
            foreach ($allNameGetters as $getterName) {
                $expectedValue = $refMethod->$getterName();
                $actualValue   = $parsedMethod->$getterName();
                $this->assertSame(
                    $expectedValue,
                    $actualValue,
                    "$getterName() for method $methodName should be equal"
                );
            }
        }
    }

    public function testCoverAllMethods()
    {
        $allInternalMethods = get_class_methods(\ReflectionMethod::class);
        $allMissedMethods   = [];

        foreach ($allInternalMethods as $internalMethodName) {
            if ('export' === $internalMethodName) {
                continue;
            }
            $refMethod    = new \ReflectionMethod(ReflectionMethod::class, $internalMethodName);
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
        $parsedRefMethod   = new ReflectionMethod(self::STUB_CLASS, 'funcWithDocAndBody');
        $originalRefMethod = new \ReflectionMethod(self::STUB_CLASS, 'funcWithDocAndBody');
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
        $this->assertEquals(self::STUB_CLASS, $retValue);

        $prototype = $refMethod->getPrototype();
        $this->assertInstanceOf(\ReflectionMethod::class, $prototype);
        $prototype->setAccessible(true);
        $retValue  = $prototype->invokeArgs(null, []);
        $this->assertNotEquals(self::STUB_CLASS, $retValue);
    }
}
