<?php
declare(strict_types=1);

namespace Go\ParserReflection;

use Go\ParserReflection\Stub\ClassWithPhp50ConstantsAndInheritance;
use Go\ParserReflection\Stub\ClassWithPhp50MagicConstants;
use Go\ParserReflection\Stub\SimplePhp50ClassWithMethodsAndProperties;
use Go\ParserReflection\Stub\ClassWithPhp50ScalarConstants;
use Go\ParserReflection\Stub\ClassWithPhp50FinalKeyword;
use Go\ParserReflection\Stub\ClassWithPhp50ImplicitAbstractKeyword;
use Go\ParserReflection\Stub\SimplePhp50AbstractClassInheritance;
use PHPUnit\Framework\Attributes\DataProvider;

class ReflectionClassTest extends AbstractTestCase
{
    /**
     * Name of the class to compare
     */
    protected static string $reflectionClassToTest = \ReflectionClass::class;

    /**
     * Tests getModifier() method
     * NB: value is masked because there are many internal constants that aren't exported in the userland
     *
     */
    #[DataProvider('classesDataProvider')]
    public function testGetModifiers(
        ReflectionClass $parsedRefClass,
        \ReflectionClass $originalRefClass
    ): void
    {
        $mask =
            \ReflectionClass::IS_EXPLICIT_ABSTRACT
            + \ReflectionClass::IS_FINAL
            + \ReflectionClass::IS_READONLY;

        $parsedModifiers   = $parsedRefClass->getModifiers() & $mask;
        $originalModifiers = $originalRefClass->getModifiers() & $mask;

        $this->assertSame(
            $originalModifiers,
            $parsedModifiers,
            "Modifiers for the {$parsedRefClass->name} should be equal"
        );
    }

    /**
     * Performs method-by-method comparison with original reflection
     */
    #[DataProvider('reflectionGetterDataProvider')]
    public function testReflectionGetterParity(
        ReflectionClass $parsedClass,
        \ReflectionClass $refClass,
        string $getterName
    ): void {
        $className = $parsedClass->getName();

        $expectedValue = $refClass->$getterName();
        $actualValue   = $parsedClass->$getterName();
        // I would like to completely stop maintaining the __toString method
        if ($expectedValue !== $actualValue && $getterName === '__toString') {
            $this->markTestSkipped("__toString for class {$className} is not equal:\n{$expectedValue}{$actualValue}");
        }
        // For Enum it isn't possible to statically resolve constants as well
        if ($parsedClass->isEnum() && $getterName === 'getConstants') {
            $this->markTestSkipped(
                "getConstants for enum {$className} couldn't be resolved fully from AST.\n" .
                "See https://github.com/goaop/parser-reflection/issues/132"
            );
        }
        $this->assertSame(
            $expectedValue,
            $actualValue,
            "{$getterName}() for class {$className} should be equal"
        );
    }

    /**
     * Provides full test-case list in the form [ReflectionClass, \ReflectionClass, getter name to check]
     */
    public static function reflectionGetterDataProvider(): \Generator
    {
        $allNameGetters = self::getGettersToCheck();
        foreach (self::classesDataProvider() as $prefix => [$parsedClass, $originalClass]) {
            foreach ($allNameGetters as $getterName) {
                yield $prefix . ', ' . $getterName => [
                    $parsedClass,
                    $originalClass,
                    $getterName
                ];
            }
        }
    }

    /**
     * Tests getMethods() returns correct number of methods for the class
     */
    #[DataProvider('classesDataProvider')]
    public function testGetMethodCount(
        ReflectionClass $parsedRefClass,
        \ReflectionClass $originalRefClass
    ): void {
        $parsedMethods     = $parsedRefClass->getMethods();
        $originalMethods   = $originalRefClass->getMethods();
        if ($parsedRefClass->getTraitAliases()) {
            $this->markTestIncomplete("Adoptation methods for traits are not supported yet");
        }
        $this->assertCount(count($originalMethods), $parsedMethods);
    }

    /**
     * Tests getReflectionConstants() returns correct number of reflectionConstants for the class
     */
    #[DataProvider('classesDataProvider')]
    public function testGetReflectionConstantCount(
        ReflectionClass $parsedRefClass,
        \ReflectionClass $originalRefClass
    ): void {
        $parsedReflectionConstants     = $parsedRefClass->getReflectionConstants();
        $originalReflectionConstants   = $originalRefClass->getReflectionConstants();
        $this->assertCount(count($originalReflectionConstants), $parsedReflectionConstants);
    }

    /**
     * Tests getProperties() returns correct number of properties for the class
     */
    #[DataProvider('classesDataProvider')]
    public function testGetProperties(
        ReflectionClass $parsedRefClass,
        \ReflectionClass $originalRefClass
    ): void {
        $parsedProperties   = $parsedRefClass->getProperties();
        $originalProperties = $originalRefClass->getProperties();
        $this->assertCount(count($originalProperties), $parsedProperties, "Count of properties for " . $originalRefClass->getName() . " should match");
    }

    /**
     * Tests getInterfaces() returns correct number of interfaces for the class
     */
    #[DataProvider('classesDataProvider')]
    public function testGetInterfaces(
        ReflectionClass $parsedRefClass,
        \ReflectionClass $originalRefClass
    ): void {
        $parsedInterfaces   = $parsedRefClass->getInterfaces();
        $originalInterfaces = $originalRefClass->getInterfaces();
        $this->assertCount(count($originalInterfaces), $parsedInterfaces, "Count of interfaces for " . $originalRefClass->getName() . " should match");
    }

    /**
     * Tests getConstructor() returns instance of ReflectionMethod for constructor
     */
    #[DataProvider('classesDataProvider')]
    public function testGetConstructor(
        ReflectionClass $parsedRefClass,
        \ReflectionClass $originalRefClass
    ): void {
        $hasConstructor = $originalRefClass->hasMethod('__construct');
        $this->assertSame(
            $hasConstructor,
            $parsedRefClass->hasMethod('__construct')
        );
        if ($hasConstructor) {
            $parsedConstructor   = $parsedRefClass->getConstructor();
            $originalConstructor = $originalRefClass->getConstructor();

            $this->assertSame($originalConstructor->getDeclaringClass()->name, $parsedConstructor->getDeclaringClass()->name);
        }
    }

    /**
     * Tests getParentClass() returns instance of ReflectionClass
     */
    #[DataProvider('classesDataProvider')]
    public function testGetParentClass(
        ReflectionClass $parsedRefClass,
        \ReflectionClass $originalRefClass
    ): void {
        $originalParentClass = $originalRefClass->getParentClass();
        $parsedParentClass   = $parsedRefClass->getParentClass();
        if (!$originalParentClass) {
            $this->assertSame($originalParentClass, $parsedParentClass);
        }
        if ($originalParentClass) {
            $this->assertSame($originalParentClass->getName(), $parsedParentClass->getName());
        }
    }

    public function testNewInstanceMethod(): void
    {
        $parsedRefClass = $this->parsedRefFileNamespace->getClass(ClassWithPhp50FinalKeyword::class);
        $instance = $parsedRefClass->newInstance();
        $this->assertInstanceOf(ClassWithPhp50FinalKeyword::class, $instance);
        $this->assertSame([], $instance->args);
    }

    public function testNewInstanceArgsMethod(): void
    {
        $someValueByRef = 5;
        $arguments      = [1, &$someValueByRef];
        $parsedRefClass = $this->parsedRefFileNamespace->getClass(ClassWithPhp50FinalKeyword::class);
        $instance       = $parsedRefClass->newInstanceArgs($arguments);
        $this->assertInstanceOf(ClassWithPhp50FinalKeyword::class, $instance);
        $this->assertSame($arguments, $instance->args);
    }

    public function testNewInstanceWithoutConstructorMethod(): void
    {
        $arguments      = [1, 2];
        $parsedRefClass = $this->parsedRefFileNamespace->getClass(ClassWithPhp50FinalKeyword::class);
        $instance       = $parsedRefClass->newInstanceWithoutConstructor($arguments);
        $this->assertInstanceOf(ClassWithPhp50FinalKeyword::class, $instance);
        $this->assertSame([], $instance->args);
    }

    public function testSetStaticPropertyValueMethod(): void
    {
        $parsedRefClass1 = $this->parsedRefFileNamespace->getClass(ClassWithPhp50ConstantsAndInheritance::class);
        $originalRefClass1 = new \ReflectionClass(ClassWithPhp50ConstantsAndInheritance::class);
        $parsedRefClass2 = $this->parsedRefFileNamespace->getClass(ClassWithPhp50MagicConstants::class);
        $originalRefClass2 = new \ReflectionClass(ClassWithPhp50MagicConstants::class);
        $defaultProp1Value = $originalRefClass1->getStaticPropertyValue('h');
        $defaultProp2Value = $originalRefClass2->getStaticPropertyValue('a');
        $ex = null;
        try {
            $this->assertEqualsWithDelta(M_PI, $parsedRefClass1->getStaticPropertyValue('h'), 0.0001, 'Close to expected value of M_PI');
            $this->assertEqualsWithDelta(M_PI, $originalRefClass1->getStaticPropertyValue('h'), 0.0001, 'Close to expected value of M_PI');
            $this->assertEquals(
                realpath(dirname(__DIR__ . parent::DEFAULT_STUB_FILENAME)),
                realpath($parsedRefClass2->getStaticPropertyValue('a')),
                'Expected value');
            $this->assertEquals(
                $originalRefClass2->getStaticPropertyValue('a'),
                $parsedRefClass2->getStaticPropertyValue('a'),
                'Same as native implementation');

            $parsedRefClass1->setStaticPropertyValue('h', 'test');
            $parsedRefClass2->setStaticPropertyValue('a', 'different value');

            $this->assertSame('test', $parsedRefClass1->getStaticPropertyValue('h'));
            $this->assertSame('test', $originalRefClass1->getStaticPropertyValue('h'));
            $this->assertSame('different value', $parsedRefClass2->getStaticPropertyValue('a'));
            $this->assertSame('different value', $originalRefClass2->getStaticPropertyValue('a'));
        }
        catch (\Exception $e) {
            $ex = $e;
        }
        // I didn't want to write a tearDown() for one test.
        $originalRefClass1->setStaticPropertyValue('h', $defaultProp1Value);
        $originalRefClass2->setStaticPropertyValue('a', $defaultProp2Value);
        if ($ex) {
            throw $ex;
        }
    }

    public function testGetMethodsFiltering(): void
    {
        $parsedRefClass   = $this->parsedRefFileNamespace->getClass(SimplePhp50ClassWithMethodsAndProperties::class);
        $originalRefClass = new \ReflectionClass(SimplePhp50ClassWithMethodsAndProperties::class);

        $parsedMethods   = $parsedRefClass->getMethods(\ReflectionMethod::IS_PUBLIC);
        $originalMethods = $originalRefClass->getMethods(\ReflectionMethod::IS_PUBLIC);

        $this->assertCount(count($originalMethods), $parsedMethods);

        $parsedMethods   = $parsedRefClass->getMethods(\ReflectionMethod::IS_PRIVATE | \ReflectionMethod::IS_STATIC);
        $originalMethods = $originalRefClass->getMethods(\ReflectionMethod::IS_PRIVATE | \ReflectionMethod::IS_STATIC);

        $this->assertCount(count($originalMethods), $parsedMethods);
    }

    public function testDirectMethods(): void
    {
        $parsedRefClass   = $this->parsedRefFileNamespace->getClass(ClassWithPhp50ImplicitAbstractKeyword::class);
        $originalRefClass = new \ReflectionClass(ClassWithPhp50ImplicitAbstractKeyword::class);

        $this->assertEquals($originalRefClass->hasMethod('test'), $parsedRefClass->hasMethod('test'));
        $this->assertCount(count($originalRefClass->getMethods()), $parsedRefClass->getMethods());

        $originalMethodName = $originalRefClass->getMethod('test')->getName();
        $parsedMethodName   = $parsedRefClass->getMethod('test')->getName();
        $this->assertSame($originalMethodName, $parsedMethodName);
    }

    public function testInheritedMethods(): void
    {
        $this->markTestIncomplete("See https://github.com/goaop/parser-reflection/issues/55");

        $parsedRefClass   = $this->parsedRefFileNamespace->getClass(SimplePhp50AbstractClassInheritance::class);
        $originalRefClass = new \ReflectionClass(SimplePhp50AbstractClassInheritance::class);

        $this->assertEquals($originalRefClass->hasMethod('test'), $parsedRefClass->hasMethod('test'));
    }

    public function testHasConstant(): void
    {
        $parsedRefClass   = $this->parsedRefFileNamespace->getClass(ClassWithPhp50ScalarConstants::class);
        $originalRefClass = new \ReflectionClass(ClassWithPhp50ScalarConstants::class);

        $this->assertSame($originalRefClass->hasConstant('D'), $parsedRefClass->hasConstant('D'));
        $this->assertSame($originalRefClass->hasConstant('E'), $parsedRefClass->hasConstant('E'));
    }

    public function testGetConstant(): void
    {
        $parsedRefClass   = $this->parsedRefFileNamespace->getClass(ClassWithPhp50ScalarConstants::class);
        $originalRefClass = new \ReflectionClass(ClassWithPhp50ScalarConstants::class);

        $this->assertSame($originalRefClass->getConstant('D'), $parsedRefClass->getConstant('D'));
        $this->assertSame($originalRefClass->getConstant('E'), $parsedRefClass->getConstant('E'));
    }

    public function testGetReflectionConstant(): void
    {
        $parsedRefClass   = $this->parsedRefFileNamespace->getClass(ClassWithPhp50ScalarConstants::class);
        $originalRefClass = new \ReflectionClass(ClassWithPhp50ScalarConstants::class);

        $this->assertFalse($parsedRefClass->getReflectionConstant('NOT_EXISTING'));
        $this->assertSame(
            (string) $originalRefClass->getReflectionConstant('D'),
            (string) $parsedRefClass->getReflectionConstant('D')
        );
        $this->assertSame(
            (string) $originalRefClass->getReflectionConstant('E'),
            (string) $parsedRefClass->getReflectionConstant('E')
        );
    }

    /**
     * Returns list of ReflectionMethod getters that be checked directly without additional arguments
     */
    protected static function getGettersToCheck(): array
    {
        return [
            'getFileName', 'getStartLine', 'getEndLine', 'getDocComment', 'getExtension', 'getExtensionName',
            'getName', 'getNamespaceName', 'getShortName', 'inNamespace',
            'isAbstract', 'isCloneable', 'isFinal', 'isInstantiable', 'isReadOnly',
            'isInterface', 'isInternal', 'isIterateable', 'isIterable', 'isTrait', 'isUserDefined',
            'getConstants', 'getTraitNames', 'getInterfaceNames', 'getStaticProperties',
            'getDefaultProperties', 'getTraitAliases', 'isEnum'
        ];
    }
}
