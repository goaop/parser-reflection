<?php
namespace ParserReflection;

use ParserReflection\Locator\ComposerLocator;
use ParserReflection\Stub\Foo;

class ReflectionParameterTest extends \PHPUnit_Framework_TestCase
{
    const STUB_FILE = '/Stub/FileWithParameters.php';

    /**
     * @var ReflectionFile
     */
    protected $parsedRefFile;

    protected function setUp()
    {
        ReflectionEngine::init(new ComposerLocator());

        $fileName = stream_resolve_include_path(__DIR__ . self::STUB_FILE);
        $fileNode = ReflectionEngine::parseFile($fileName);

        $reflectionFile = new ReflectionFile($fileName, $fileNode);
        $this->parsedRefFile = $reflectionFile;

        include_once $fileName;
    }

    public function testGeneralInfoGetters()
    {
        $allNameGetters = [
            'isArray', 'isCallable', 'isOptional', 'isPassedByReference', 'isDefaultValueAvailable',
            'getPosition', 'canBePassedByValue', 'allowsNull', 'getDefaultValue', 'getDefaultValueConstantName',
            'isDefaultValueConstant'
        ];
        $onlyWithDefaultValues = array_flip([
            'getDefaultValue', 'getDefaultValueConstantName', 'isDefaultValueConstant'
        ]);
        if (PHP_VERSION_ID >= 50600) {
            $allNameGetters[] = 'isVariadic';
        }

        foreach ($this->parsedRefFile->getFileNamespaces() as $fileNamespace) {
            foreach ($fileNamespace->getFunctions() as $refFunction) {
                $functionName = $refFunction->getName();
                foreach ($refFunction->getParameters() as $refParameter) {
                    $parameterName        = $refParameter->getName();
                    $originalRefParameter = new \ReflectionParameter($functionName, $parameterName);
                    foreach ($allNameGetters as $getterName) {

                        // skip some methods if there is no default value
                        $isDefaultValueAvailable = $originalRefParameter->isDefaultValueAvailable();
                        if (isset($onlyWithDefaultValues[$getterName]) && !$isDefaultValueAvailable) {
                            continue;
                        }
                        $expectedValue = $originalRefParameter->$getterName();
                        $actualValue   = $refParameter->$getterName();
                        $this->assertEquals(
                            $expectedValue,
                            $actualValue,
                            "{$getterName}() for parameter {$functionName}:{$parameterName} should be equal"
                        );
                    }
                }
            }
        }
    }

    public function testGetClassMethod()
    {
        $parsedNamespace = $this->parsedRefFile->getFileNamespace('ParserReflection\Stub');
        $parsedFunction  = $parsedNamespace->getFunction('miscParameters');

        $parameters = $parsedFunction->getParameters();
        $this->assertSame(null, $parameters[0 /* array $arrayParam*/]->getClass());
        $this->assertSame(null, $parameters[3 /* callable $callableParam */]->getClass());

        $objectParam = $parameters[5 /* \stdClass $objectParam */]->getClass();
        $this->assertInstanceOf(\ReflectionClass::class, $objectParam);
        $this->assertSame(\stdClass::class, $objectParam->getName());

        $typehintedParamWithNs = $parameters[7 /* ReflectionParameter $typehintedParamWithNs */]->getClass();
        $this->assertInstanceOf(\ReflectionClass::class, $typehintedParamWithNs);
        $this->assertSame(ReflectionParameter::class, $typehintedParamWithNs->getName());
    }


    public function testGetDeclaringClassMethodReturnsObject()
    {
        $parsedNamespace = $this->parsedRefFile->getFileNamespace('ParserReflection\Stub');
        $parsedClass     = $parsedNamespace->getClass(Foo::class);
        $parsedFunction  = $parsedClass->getMethod('methodParam');

        $parameters = $parsedFunction->getParameters();
        $this->assertSame($parsedClass->getName(), $parameters[0]->getDeclaringClass()->getName());
    }

    public function testGetDeclaringClassMethodReturnsNull()
    {
        $parsedNamespace = $this->parsedRefFile->getFileNamespace('ParserReflection\Stub');
        $parsedFunction  = $parsedNamespace->getFunction('miscParameters');

        $parameters = $parsedFunction->getParameters();
        $this->assertFalse($parameters[0]->getDeclaringClass());
    }

    public function testCoverAllMethods()
    {
        $allInternalMethods = get_class_methods(\ReflectionParameter::class);
        $allMissedMethods   = [];

        foreach ($allInternalMethods as $internalMethodName) {
            if ('export' === $internalMethodName) {
                continue;
            }
            $refMethod    = new \ReflectionMethod(ReflectionParameter::class, $internalMethodName);
            $definerClass = $refMethod->getDeclaringClass()->getName();
            if (strpos($definerClass, 'ParserReflection') !== 0) {
                $allMissedMethods[] = $internalMethodName;
            }
        }

        if ($allMissedMethods) {
            $this->markTestIncomplete('Methods ' . join($allMissedMethods, ', ') . ' are not implemented');
        }
    }
}