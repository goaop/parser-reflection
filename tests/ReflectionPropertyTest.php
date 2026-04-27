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
        // Covers: ReflectionProperty::getDefaultValue() for a property without a default value is deprecated
        if ($getterName === 'getDefaultValue' && !$refProperty->hasDefaultValue()) {
            $this->markTestSkipped("Skipping getDefaultValue() for a property without a default value, it is deprecated");
        }

        // Covers: misc accessors for a trait property without an object
        if (in_array($getterName, ['getValue', 'getDefaultValue', 'isInitialized', '__toString'], true) && $parsedClass->isTrait()) {
            $this->markTestSkipped("Skipping accessing trait property without a class, it is deprecated");
        }
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
        if ($originalRefProperty->isStatic() && !$parsedRefClass->isTrait()) {
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

    #[DataProvider('propertiesDataProvider')]
    public function testGetSettableType(
        ReflectionClass $parsedRefClass,
        \ReflectionProperty $originalRefProperty,
    ): void {
        if (PHP_VERSION_ID < 80400) {
            $this->markTestSkipped('getSettableType() requires PHP 8.4+');
        }

        $propertyName      = $originalRefProperty->getName();
        $className         = $parsedRefClass->getName();
        $parsedRefProperty = $parsedRefClass->getProperty($propertyName);

        $originalSettable = $originalRefProperty->getSettableType();
        $parsedSettable   = $parsedRefProperty->getSettableType();

        $message = "Settable type for {$className}::{$propertyName} should match native reflection";
        if ($originalSettable === null) {
            $this->assertNull($parsedSettable, $message);
        } else {
            $this->assertNotNull($parsedSettable, $message);
            $this->assertSame($originalSettable->__toString(), $parsedSettable->__toString(), $message);
            $this->assertSame($originalSettable->allowsNull(), $parsedSettable->allowsNull(), $message);
        }
    }

    #[DataProvider('propertyHooksDataProvider')]
    public function testHasHookMethod(
        ReflectionClass $parsedRefClass,
        \ReflectionProperty $originalRefProperty,
        \PropertyHookType $hookType
    ): void {
        $propertyName      = $originalRefProperty->getName();
        $className         = $parsedRefClass->getName();
        $parsedRefProperty = $parsedRefClass->getProperty($propertyName);

        $this->assertSame(
            $originalRefProperty->hasHook($hookType),
            $parsedRefProperty->hasHook($hookType),
            "Presence of {$hookType->value} hook for property {$className}:{$propertyName} should be equal"
        );
    }

    #[DataProvider('propertyHooksDataProvider')]
    public function testGetHookMethod(
        ReflectionClass $parsedRefClass,
        \ReflectionProperty $originalRefProperty,
        \PropertyHookType $hookType
    ): void {
        $propertyName      = $originalRefProperty->getName();
        $className         = $parsedRefClass->getName();
        $parsedRefProperty = $parsedRefClass->getProperty($propertyName);

        $originalHook = $originalRefProperty->getHook($hookType);
        $parsedHook   = $parsedRefProperty->getHook($hookType);

        if ($originalHook === null) {
            $this->assertNull(
                $parsedHook,
                "getHook({$hookType->value}) for property {$className}:{$propertyName} should be null"
            );
        } else {
            $this->assertNotNull($parsedHook, "getHook({$hookType->value}) for property {$className}:{$propertyName} should not be null");
            $this->assertSame(
                $originalHook->getName(),
                $parsedHook->getName(),
                "Hook method name for {$className}:{$propertyName}::{$hookType->value} should be equal"
            );
            $this->assertSame(
                $originalHook->getNumberOfParameters(),
                $parsedHook->getNumberOfParameters(),
                "Hook parameter count for {$className}:{$propertyName}::{$hookType->value} should be equal"
            );
            if ($originalHook->hasReturnType()) {
                $this->assertTrue($parsedHook->hasReturnType(), "Hook should have return type");
                $this->assertSame(
                    $originalHook->getReturnType()->__toString(),
                    $parsedHook->getReturnType()->__toString(),
                    "Hook return type for {$className}:{$propertyName}::{$hookType->value} should be equal"
                );
            }
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
     * Provides full test-case list in the form [ParsedClass, \ReflectionProperty to check, hook type]
     */
    public static function propertyHooksDataProvider(): \Generator
    {
        if (PHP_VERSION_ID < 80400) {
            return;
        }

        foreach (self::propertiesDataProvider() as $prefix => [$parsedClass, $classProperty]) {
            foreach (\PropertyHookType::cases() as $hookType) {
                yield $prefix . ' ' . $parsedClass->getName() . '->' . $classProperty->getName() . ' hook:' . $hookType->value => [
                    $parsedClass,
                    $classProperty,
                    $hookType,
                ];
            }
        }
    }

    /**
     * Returns list of ReflectionMethod getters that be checked directly without additional arguments
     */
    protected static function getGettersToCheck(): array
    {
        $getters = [
            'isDefault', 'getName', 'getModifiers', 'getDocComment',
            'isPrivate', 'isProtected', 'isPublic', 'isStatic', 'isReadOnly', 'isInitialized',
            'hasType', 'hasDefaultValue', 'getDefaultValue', '__toString'
        ];

        if (PHP_VERSION_ID >= 80400) {
            array_push($getters, 'isAbstract', 'isProtectedSet', 'isPrivateSet', 'isFinal', 'hasHooks', 'isVirtual');
        }

        return $getters;
    }
}
