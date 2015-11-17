<?php
namespace ParserReflection;

use ParserReflection\Locator\ComposerLocator;

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
            'getPosition', 'canBePassedByValue', 'allowsNull'/*, 'getDefaultValue', 'getDefaultValueConstantName',
            'isDefaultValueConstant'*/
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