<?php
declare(strict_types=1);

namespace Go\ParserReflection;

use Go\ParserReflection\Stub\SimplePhp50ClassWithProperties;
use PHPUnit\Framework\Attributes\DataProvider;

class ReflectionPropertyTest extends AbstractTestCase
{
    /**
     * Class to test
     */
    protected static string $reflectionClassToTest = \ReflectionProperty::class;

    /**
     * Class to load
     */
    protected static string $defaultClassToLoad = SimplePhp50ClassWithProperties::class;

    /**
     * Performs method-by-method comparison with original reflection
     *
     * @param ReflectionClass     $parsedClass Parsed class
     * @param \ReflectionProperty $refProperty Property to analyze
     * @param string              $getterName  Name of the reflection method to test
     */
    #[DataProvider('reflectionGetterDataProvider')]
    public function testReflectionGetterParity(
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
     * Provides full test-case list in the form [ReflectionClass, \ReflectionProperty, getter name to check]
     */
    public static function reflectionGetterDataProvider(): \Generator
    {
        $allNameGetters = self::getGettersToCheck();
        foreach (self::propertiesDataProvider() as $prefix => [$parsedClass, $classProperty]) {
            foreach ($allNameGetters as $getterName) {
                // We could check isInitialized only for static properties
                if ($getterName === 'isInitialized' && !$classProperty->isStatic()) {
                    continue;
                }
                yield $prefix . ', ' . $getterName => [
                    $parsedClass,
                    $classProperty,
                    $getterName
                ];
            }
        }
    }

    #[DataProvider('propertiesDataProvider')]
    public function testGetDefaultValue(ReflectionClass $parsedRefClass, \ReflectionProperty $originalRefProperty): void
    {
        $propertyName   = $originalRefProperty->getName();
        $parsedProperty = $parsedRefClass->getProperty($propertyName);
        $className      = $parsedRefClass->getName();
        $this->assertSame(
            $originalRefProperty->hasDefaultValue(),
            $parsedProperty->hasDefaultValue(),
            "Presence of default value for property {$className}:{$propertyName} should be equal"
        );
        if ($originalRefProperty->isStatic()) {
            $actualValue = $parsedProperty->getValue();
            $this->assertSame($originalRefProperty->getValue(), $actualValue);
        } elseif ($originalRefProperty->hasDefaultValue() && $parsedRefClass->isInstantiable()) {
            $instance    = $parsedRefClass->newInstanceWithoutConstructor();
            $actualValue = $parsedProperty->getValue(); // Let's try not to fallback to internal reflection, pure AST
            $this->assertSame($originalRefProperty->getValue($instance), $actualValue);
        }
    }

    public function testGetSetValueForObjectMethods(): void
    {
        $parsedProperty = $this->parsedRefClass->getProperty('protectedProperty');

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

    #[DataProvider('propertiesDataProvider')]
    public function testDebugInfoMethod(ReflectionClass $parsedRefClass, \ReflectionProperty $originalRefProperty): void
    {
        $propertyName      = $originalRefProperty->getName();
        $className         = $parsedRefClass->getName();
        $parsedRefProperty = $parsedRefClass->getProperty($propertyName);
        if (!$parsedRefProperty instanceof ReflectionProperty) {
            $this->markTestSkipped("Native reflection property {$className}->{$propertyName} represented similarly");
        }
        $expectedValue = (array) $originalRefProperty;
        $this->assertSame($expectedValue, $parsedRefProperty->__debugInfo());
    }

    #[DataProvider('propertiesDataProvider')]
    public function testGetTypeMethod(ReflectionClass $parsedRefClass, \ReflectionProperty $originalRefProperty): void
    {
        $propertyName      = $originalRefProperty->getName();
        $className         = $parsedRefClass->getName();
        $parsedRefProperty = $parsedRefClass->getProperty($propertyName);

        $hasType = $parsedRefProperty->hasType();
        $this->assertSame(
            $originalRefProperty->hasType(),
            $hasType,
            "Presence of type for property {$className}:{$propertyName} should be equal"
        );
        $message= "Type information for {$className}::$propertyName not equals to the original reflection";
        if ($hasType) {
            $parsedType   = $parsedRefProperty->getType();
            $originalType = $originalRefProperty->getType();
            $this->assertSame($originalType->allowsNull(), $parsedType->allowsNull(), $message);
            $this->assertSame($originalType->__toString(), $parsedType->__toString(), $message);
        } else {
            $this->assertSame(
                $originalRefProperty->getType(),
                $parsedRefProperty->getType(),
                $message
            );
        }
    }

    /**
     * Provides full test-case list in the form [ParsedClass, \ReflectionProperty to check]
     */
    public static function propertiesDataProvider(): \Generator
    {
        foreach (self::classesDataProvider() as $prefix => [$parsedClass, $refClass]) {
            foreach ($refClass->getProperties() as $classProperty) {
                $fullPropertyName = $parsedClass->getName() . '->' . $classProperty->getName();
                yield $prefix . ' ' . $fullPropertyName => [
                    $parsedClass,
                    $classProperty,
                ];
            }
        }
    }

    /**
     * Returns list of ReflectionMethod getters that be checked directly without additional arguments
     */
    protected static function getGettersToCheck(): array
    {
        return [
            'isDefault', 'getName', 'getModifiers', 'getDocComment',
            'isPrivate', 'isProtected', 'isPublic', 'isStatic', 'isReadOnly', 'isInitialized',
            'hasType', 'hasDefaultValue', 'getDefaultValue', '__toString'
        ];
    }
}
