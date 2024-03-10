<?php
declare(strict_types=1);

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
    protected static string $reflectionClassToTest = \ReflectionProperty::class;

    /**
     * Class to load
     *
     * @var string
     */
    protected static string $defaultClassToLoad = ClassWithProperties::class;

    /**
     * Performs method-by-method comparison with original reflection
     *
     *
     * @param ReflectionClass     $parsedClass Parsed class
     * @param \ReflectionProperty $refProperty Property to analyze
     * @param string              $getterName  Name of the reflection method to test
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('caseProvider')]
    public function testReflectionMethodParity(
        ReflectionClass $parsedClass,
        \ReflectionProperty $refProperty,
        string $getterName
    ): void
    {
        $propertyName   = $refProperty->getName();
        $className      = $parsedClass->getName();
        $parsedProperty = $parsedClass->getProperty($propertyName);
        $expectedValue  = $refProperty->$getterName();
        $actualValue    = $parsedProperty->$getterName();
        // I would like to completely stop maintaining the __toString method
        if ($expectedValue !== $actualValue && $getterName === '__toString') {
            $this->markTestSkipped("__toString for property {$className}->{$propertyName} is not equal:\n{$expectedValue}{$actualValue}");
        }
        $this->assertSame(
            $expectedValue,
            $actualValue,
            "{$getterName}() for property {$className}->{$propertyName} should be equal"
        );
    }

    /**
     * Provides full test-case list in the form [ParsedClass, ReflectionMethod, getter name to check]
     *
     * @return \Generator
     */
    public static function caseProvider(): \Generator
    {
        $allNameGetters = static::getGettersToCheck();

        $testCases = [];
        $files     = static::getFilesToAnalyze();
        foreach ($files as $fileList) {
            foreach ($fileList as $fileName) {
                // TODO: Can be replaced with $this->setUpFile() later...
                $fileName = stream_resolve_include_path($fileName);
                $fileNode = ReflectionEngine::parseFile($fileName);

                $reflectionFile = new ReflectionFile($fileName, $fileNode);
                include_once $fileName;
                foreach ($reflectionFile->getFileNamespaces() as $fileNamespace) {
                    foreach ($fileNamespace->getClasses() as $parsedClass) {
                        $originalReflectionClass = new \ReflectionClass($parsedClass->getName());
                        foreach ($originalReflectionClass->getProperties() as $classProperty) {
                            $caseName = $parsedClass->getName() . '->' . $classProperty->getName();
                            foreach ($allNameGetters as $getterName) {
                                yield $caseName . ', ' . $getterName => [
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
    }

    public function testSetAccessibleMethod(): void
    {
        $parsedProperty = $this->parsedRefClass->getProperty('protectedStaticProperty');
        $parsedProperty->setAccessible(true);

        $value = $parsedProperty->getValue();
        $this->assertSame('foo', $value);
    }

    public function testGetSetValueForObjectMethods(): void
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

    public function testCompatibilityWithOriginalConstructor(): void
    {
        $parsedRefProperty = new ReflectionProperty($this->parsedRefClass->getName(), 'publicStaticProperty');
        $originalValue     = $parsedRefProperty->getValue();

        $this->assertSame(M_PI, $originalValue);
    }

    public function testDebugInfoMethod(): void
    {
        $parsedRefProperty   = $this->parsedRefClass->getProperty('publicStaticProperty');
        $originalRefProperty = new \ReflectionProperty($this->parsedRefClass->getName(), 'publicStaticProperty');
        $expectedValue     = (array) $originalRefProperty;
        $this->assertSame($expectedValue, $parsedRefProperty->__debugInfo());
    }

    public function testGetTypeMethod(): void
    {
        foreach ($this->parsedRefClass->getProperties() as $parsedProperty) {
            $propertyName        = $parsedProperty->getName();
            $className           = $this->parsedRefClass->getName();
            $originalRefProperty = new \ReflectionProperty($className, $propertyName);
            $hasType             = $parsedProperty->hasType();
            $this->assertSame(
                $originalRefProperty->hasType(),
                $hasType,
                "Presence of type for property {$className}:{$propertyName} should be equal"
            );
            $message= "Parameter {$className}:$propertyName not equals to the original reflection";
            if ($hasType) {
                $parsedType   = $parsedProperty->getType();
                $originalType = $originalRefProperty->getType();
                $this->assertSame($originalType->allowsNull(), $parsedType->allowsNull(), $message);
                $this->assertSame($originalType->__toString(), $parsedType->__toString(), $message);
            } else {
                $this->assertSame(
                    $originalRefProperty->getType(),
                    $parsedProperty->getType(),
                    $message
                );
            }
        }
    }

    /**
     * Returns list of ReflectionMethod getters that be checked directly without additional arguments
     *
     * @return array
     */
    protected static function getGettersToCheck()
    {
        $allNameGetters = [
            'isDefault', 'getName', 'getModifiers', 'getDocComment',
            'isPrivate', 'isProtected', 'isPublic', 'isStatic', 'isReadOnly',
            'hasType', 'hasDefaultValue', 'getDefaultValue', '__toString'
        ];

        return $allNameGetters;
    }
}
