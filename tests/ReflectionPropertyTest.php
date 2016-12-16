<?php
namespace Go\ParserReflection;

use Go\ParserReflection\Stub\ClassWithProperties;
use PhpParser\Lexer;

class ReflectionPropertyTest extends AbstractTestCase
{
    /**
     * Class to test
     *
     * @var string
     */
    protected static $reflectionClassToTest = \ReflectionProperty::class;

    /**
     * Class to load
     *
     * @var string
     */
    protected static $defaultClassToLoad = ClassWithProperties::class;

    /**
     * Performs method-by-method comparison with original reflection
     *
     * @dataProvider caseProvider
     *
     * @param ReflectionClass     $parsedClass Parsed class
     * @param \ReflectionProperty $refProperty Property to analyze
     * @param string              $getterName  Name of the reflection method to test
     */
    public function testReflectionMethodParity(
        ReflectionClass $parsedClass,
        \ReflectionProperty $refProperty,
        $getterName
    )
    {
        $propertyName   = $refProperty->getName();
        $className      = $parsedClass->getName();
        $parsedProperty = $parsedClass->getProperty($propertyName);
        $expectedValue  = $refProperty->$getterName();
        $actualValue    = $parsedProperty->$getterName();
        $this->assertSame(
            $expectedValue,
            $actualValue,
            "{$getterName}() for property {$className}->{$propertyName} should be equal"
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

        $testCases = [];
        $files     = $this->getFilesToAnalyze();
        foreach ($files as $fileList) {
            foreach ($fileList as $fileName) {
                $fileName = stream_resolve_include_path($fileName);
                $fileNode = ReflectionEngine::parseFile($fileName);

                $reflectionFile = new ReflectionFile($fileName, $fileNode);
                include_once $fileName;
                foreach ($reflectionFile->getFileNamespaces() as $fileNamespace) {
                    foreach ($fileNamespace->getClasses() as $parsedClass) {
                        $refClass = new \ReflectionClass($parsedClass->getName());
                        foreach ($refClass->getProperties() as $classProperty) {
                            $caseName = $parsedClass->getName() . '->' . $classProperty->getName();
                            foreach ($allNameGetters as $getterName) {
                                $testCases[$caseName . ', ' . $getterName] = [
                                    $parsedClass,
                                    $classProperty,
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

    public function testSetAccessibleMethod()
    {
        $parsedProperty = $this->parsedRefClass->getProperty('protectedStaticProperty');
        $parsedProperty->setAccessible(true);

        $value = $parsedProperty->getValue();
        $this->assertSame('foo', $value);
    }

    public function testGetSetValueForObjectMethods()
    {
        $parsedProperty = $this->parsedRefClass->getProperty('protectedProperty');
        $parsedProperty->setAccessible(true);

        $className = $this->parsedRefClass->getName();
        $obj       = new $className;

        $value = $parsedProperty->getValue($obj);
        $this->assertSame('a', $value);

        $parsedProperty->setValue($obj, 43);
        $value = $parsedProperty->getValue($obj);
        $this->assertSame(43, $value);
    }

    public function testCompatibilityWithOriginalConstructor()
    {
        $parsedRefProperty = new ReflectionProperty($this->parsedRefClass->getName(), 'publicStaticProperty');
        $originalValue     = $parsedRefProperty->getValue();

        $this->assertSame(M_PI, $originalValue);
    }

    public function testDebugInfoMethod()
    {
        $parsedRefProperty   = $this->parsedRefClass->getProperty('publicStaticProperty');
        $originalRefProperty = new \ReflectionProperty($this->parsedRefClass->getName(), 'publicStaticProperty');
        $expectedValue     = (array) $originalRefProperty;
        $this->assertSame($expectedValue, $parsedRefProperty->___debugInfo());
    }

    /**
     * Returns list of ReflectionMethod getters that be checked directly without additional arguments
     *
     * @return array
     */
    protected function getGettersToCheck()
    {
        $allNameGetters = [
            'isDefault', 'getName', 'getModifiers', 'getDocComment',
            'isPrivate', 'isProtected', 'isPublic', 'isStatic', '__toString'
        ];

        return $allNameGetters;
    }
}
