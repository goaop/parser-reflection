<?php
/**
 * Parser Reflection API
 *
 * @copyright Copyright 2015-2022, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 *
 * @noinspection PhpDocMissingThrowsInspection
 * @noinspection PhpUnhandledExceptionInspection
 */
declare(strict_types=1);

namespace Go\ParserReflection;

use Go\ParserReflection\Stub\ClassWithArrays;
use Go\ParserReflection\Stub\ClassWithConstantsAndInheritance;
use Go\ParserReflection\Stub\ClassWithDifferentConstantTypes;
use Go\ParserReflection\Stub\ClassWithProperties;
use ReflectionClass as BaseReflectionClass;
use ReflectionProperty as BaseReflectionProperty;

class ReflectionPropertyTest extends AbstractTestCase
{
    /**
     * Class to test
     *
     * @var string
     */
    protected static string $reflectionClassToTest = BaseReflectionProperty::class;

    /**
     * Class to load
     *
     * @var string
     */
    protected static string $defaultClassToLoad = ClassWithProperties::class;

    /**
     * Performs method-by-method comparison with original reflection
     *
     * @dataProvider caseProvider
     *
     * @param ReflectionClass        $parsedClass Parsed class
     * @param BaseReflectionProperty $refProperty Property to analyze
     * @param string                 $getterName  Name of the reflection method to test
     *
     * @throws ReflectionException
     */
    public function testReflectionMethodParity(
        ReflectionClass        $parsedClass,
        BaseReflectionProperty $refProperty,
        string                 $getterName
    ) {
        $propertyName   = $refProperty->getName();
        $className      = $parsedClass->getName();

        // TODO
        if (($className === ClassWithConstantsAndInheritance::class
            || $className === ClassWithDifferentConstantTypes::class)
            && ($propertyName === 'h' || $propertyName === 'refConst')
        ) {
            $this->markTestIncomplete('Outside constants are printed as "self::CONSTANT_NAME"');
        }

        // TODO
        if ($className === ClassWithDifferentConstantTypes::class
           && $propertyName === 'refArray'
        ) {
            $this->markTestIncomplete('Outside constants inside arrays replaces the array with "self::CONSTANT_NAME"');
        }

        // TODO
        if ($className === ClassWithArrays::class
            && preg_match('/[aA]rray.*/', $propertyName)
        ) {
            $this->markTestIncomplete('Arrays must be fully parsed');
        }

        // TODO
        if ($getterName === '__toString') {
            $this->markTestIncomplete('Constructor property promotion for __toString() is not supported');
        }

        $parsedProperty = $parsedClass->getProperty($propertyName);
        $expectedValue  = $refProperty->$getterName();
        $actualValue    = $parsedProperty->$getterName();
        $this->assertSame(
            $expectedValue,
            $actualValue,
            "$getterName() for property $className->$propertyName should be equal"
        );
    }

    /**
     * Provides full test-case list in the form [ParsedClass, ReflectionMethod, getter name to check]
     *
     * @return array
     */
    public function caseProvider(): array
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
                        $refClass = new BaseReflectionClass($parsedClass->getName());
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
        $originalRefProperty = new BaseReflectionProperty($this->parsedRefClass->getName(), 'publicStaticProperty');
        $expectedValue     = (array) $originalRefProperty;
        $this->assertSame($expectedValue, $parsedRefProperty->__debugInfo());
    }

    /**
     * Returns list of ReflectionMethod getters that be checked directly without additional arguments
     *
     * @return array
     */
    protected function getGettersToCheck(): array
    {
        $allNameGetters = [
            'isDefault', 'getName', 'getModifiers', 'getDocComment',
            'isPrivate', 'isProtected', 'isPublic', 'isStatic', '__toString'
        ];

        return $allNameGetters;
    }
}
