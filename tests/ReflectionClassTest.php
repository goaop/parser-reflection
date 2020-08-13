<?php
namespace Go\ParserReflection;

use Go\ParserReflection\Stub\ClassWithConstantsAndInheritance;
use Go\ParserReflection\Stub\ClassWithMagicConstants;
use Go\ParserReflection\Stub\ClassWithMethodsAndProperties;
use Go\ParserReflection\Stub\ClassWithScalarConstants;
use Go\ParserReflection\Stub\FinalClass;
use Go\ParserReflection\Stub\ImplicitAbstractClass;
use Go\ParserReflection\Stub\SimpleAbstractInheritance;

class ReflectionClassTest extends AbstractTestCase
{
    /**
     * Name of the class to compare
     *
     * @var string
     */
    protected static $reflectionClassToTest = \ReflectionClass::class;

    /**
     * Tests getModifier() method
     * NB: value is masked because there are many internal constants that aren't exported in the userland
     *
     * @dataProvider getFilesToAnalyze
     *
     * @param string $fileName File name to test
     */
    public function testGetModifiers($fileName)
    {
        if (PHP_VERSION_ID >= 70400) {
            $this->markTestSkipped('TODO: Fix mapping and logic of modifiers');
        }
        $mask =
            \ReflectionClass::IS_EXPLICIT_ABSTRACT
            + \ReflectionClass::IS_FINAL
            + \ReflectionClass::IS_IMPLICIT_ABSTRACT;

        $this->setUpFile($fileName);
        $parsedClasses = $this->parsedRefFileNamespace->getClasses();
        foreach ($parsedClasses as $parsedRefClass) {
            $originalRefClass  = new \ReflectionClass($parsedRefClass->getName());
            $parsedModifiers   = $parsedRefClass->getModifiers() & $mask;
            $originalModifiers = $originalRefClass->getModifiers() & $mask;

            $this->assertEquals($originalModifiers, $parsedModifiers);
        }
    }

    /**
     * Performs method-by-method comparison with original reflection
     *
     * @dataProvider caseProvider
     *
     * @param ReflectionClass   $parsedClass Parsed class
     * @param \ReflectionMethod $refMethod Method to analyze
     * @param string                  $getterName Name of the reflection method to test
     */
    public function testReflectionMethodParity(
        ReflectionClass $parsedClass,
        $getterName
    ) {
        $className = $parsedClass->getName();
        $refClass  = new \ReflectionClass($className);

        $expectedValue = $refClass->$getterName();
        $actualValue   = $parsedClass->$getterName();
        $this->assertSame(
            $expectedValue,
            $actualValue,
            "{$getterName}() for class {$className} should be equal"
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
                        $caseName = $parsedClass->getName();
                        foreach ($allNameGetters as $getterName) {
                            $testCases[$caseName . ', ' . $getterName] = [
                                $parsedClass,
                                $getterName
                            ];
                        }
                    }
                }
            }
        }

        return $testCases;
    }

    /**
     * Tests getMethods() returns correct number of methods for the class
     *
     * @dataProvider getFilesToAnalyze
     *
     * @param string $fileName File name to test
     */
    public function testGetMethodCount($fileName)
    {
        $this->setUpFile($fileName);
        $parsedClasses = $this->parsedRefFileNamespace->getClasses();

        foreach ($parsedClasses as $parsedRefClass) {
            $originalRefClass  = new \ReflectionClass($parsedRefClass->getName());
            $parsedMethods     = $parsedRefClass->getMethods();
            $originalMethods   = $originalRefClass->getMethods();
            if ($parsedRefClass->getTraitAliases()) {
                $this->markTestIncomplete("Adoptation methods for traits are not supported yet");
            }
            $this->assertCount(count($originalMethods), $parsedMethods);
        }
    }

    /**
     * Tests getReflectionConstants() returns correct number of reflectionConstants for the class
     *
     * @dataProvider getFilesToAnalyze
     *
     * @param string $fileName File name to test
     */
    public function testGetReflectionConstantCount($fileName)
    {
        $this->setUpFile($fileName);
        $parsedClasses = $this->parsedRefFileNamespace->getClasses();

        foreach ($parsedClasses as $parsedRefClass) {
            $originalRefClass  = new \ReflectionClass($parsedRefClass->getName());
            $parsedReflectionConstants     = $parsedRefClass->getReflectionConstants();
            $originalReflectionConstants   = $originalRefClass->getReflectionConstants();
            $this->assertCount(count($originalReflectionConstants), $parsedReflectionConstants);
        }
    }



    /**
     * Tests getProperties() returns correct number of properties for the class
     *
     * @dataProvider getFilesToAnalyze
     *
     * @param string $fileName File name to test
     */
    public function testGetProperties($fileName)
    {
        $this->setUpFile($fileName);
        $parsedClasses = $this->parsedRefFileNamespace->getClasses();

        foreach ($parsedClasses as $parsedRefClass) {
            $originalRefClass  = new \ReflectionClass($parsedRefClass->getName());
            $parsedProperties   = $parsedRefClass->getProperties();
            $originalProperties = $originalRefClass->getProperties();

            $this->assertCount(count($originalProperties), $parsedProperties);
        }
    }

    public function testNewInstanceMethod()
    {
        $parsedRefClass = $this->parsedRefFileNamespace->getClass(FinalClass::class);
        $instance = $parsedRefClass->newInstance();
        $this->assertInstanceOf(FinalClass::class, $instance);
        $this->assertSame([], $instance->args);
    }

    public function testNewInstanceArgsMethod()
    {
        $someValueByRef = 5;
        $arguments      = [1, &$someValueByRef];
        $parsedRefClass = $this->parsedRefFileNamespace->getClass(FinalClass::class);
        $instance       = $parsedRefClass->newInstanceArgs($arguments);
        $this->assertInstanceOf(FinalClass::class, $instance);
        $this->assertSame($arguments, $instance->args);
    }

    public function testNewInstanceWithoutConstructorMethod()
    {
        $arguments      = [1, 2];
        $parsedRefClass = $this->parsedRefFileNamespace->getClass(FinalClass::class);
        $instance       = $parsedRefClass->newInstanceWithoutConstructor($arguments);
        $this->assertInstanceOf(FinalClass::class, $instance);
        $this->assertSame([], $instance->args);
    }

    public function testSetStaticPropertyValueMethod()
    {
        $parsedRefClass1 = $this->parsedRefFileNamespace->getClass(ClassWithConstantsAndInheritance::class);
        $originalRefClass1 = new \ReflectionClass(ClassWithConstantsAndInheritance::class);
        $parsedRefClass2 = $this->parsedRefFileNamespace->getClass(ClassWithMagicConstants::class);
        $originalRefClass2 = new \ReflectionClass(ClassWithMagicConstants::class);
        $defaultProp1Value = $originalRefClass1->getStaticPropertyValue('h');
        $defaultProp2Value = $originalRefClass2->getStaticPropertyValue('a');
        $ex = null;
        try {
            $this->assertEquals(M_PI, $parsedRefClass1->getStaticPropertyValue('h'), 'Close to expected value of M_PI', 0.0001);
            $this->assertEquals(M_PI, $originalRefClass1->getStaticPropertyValue('h'), 'Close to expected value of M_PI', 0.0001);
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

    public function testGetMethodsFiltering()
    {
        $parsedRefClass   = $this->parsedRefFileNamespace->getClass(ClassWithMethodsAndProperties::class);
        $originalRefClass = new \ReflectionClass(ClassWithMethodsAndProperties::class);

        $parsedMethods   = $parsedRefClass->getMethods(\ReflectionMethod::IS_PUBLIC);
        $originalMethods = $originalRefClass->getMethods(\ReflectionMethod::IS_PUBLIC);

        $this->assertCount(count($originalMethods), $parsedMethods);

        $parsedMethods   = $parsedRefClass->getMethods(\ReflectionMethod::IS_PRIVATE | \ReflectionMethod::IS_STATIC);
        $originalMethods = $originalRefClass->getMethods(\ReflectionMethod::IS_PRIVATE | \ReflectionMethod::IS_STATIC);

        $this->assertCount(count($originalMethods), $parsedMethods);
    }

    public function testDirectMethods()
    {
        $parsedRefClass   = $this->parsedRefFileNamespace->getClass(ImplicitAbstractClass::class);
        $originalRefClass = new \ReflectionClass(ImplicitAbstractClass::class);

        $this->assertEquals($originalRefClass->hasMethod('test'), $parsedRefClass->hasMethod('test'));
        $this->assertCount(count($originalRefClass->getMethods()), $parsedRefClass->getMethods());

        $originalMethodName = $originalRefClass->getMethod('test')->getName();
        $parsedMethodName   = $parsedRefClass->getMethod('test')->getName();
        $this->assertSame($originalMethodName, $parsedMethodName);
    }

    public function testInheritedMethods()
    {
        $this->markTestIncomplete("See https://github.com/goaop/parser-reflection/issues/55");

        $parsedRefClass   = $this->parsedRefFileNamespace->getClass(SimpleAbstractInheritance::class);
        $originalRefClass = new \ReflectionClass(SimpleAbstractInheritance::class);

        $this->assertEquals($originalRefClass->hasMethod('test'), $parsedRefClass->hasMethod('test'));
    }

    public function testHasConstant()
    {
        $parsedRefClass   = $this->parsedRefFileNamespace->getClass(ClassWithScalarConstants::class);
        $originalRefClass = new \ReflectionClass(ClassWithScalarConstants::class);

        $this->assertSame($originalRefClass->hasConstant('D'), $parsedRefClass->hasConstant('D'));
        $this->assertSame($originalRefClass->hasConstant('E'), $parsedRefClass->hasConstant('E'));
    }

    public function testGetConstant()
    {
        $parsedRefClass   = $this->parsedRefFileNamespace->getClass(ClassWithScalarConstants::class);
        $originalRefClass = new \ReflectionClass(ClassWithScalarConstants::class);

        $this->assertSame($originalRefClass->getConstant('D'), $parsedRefClass->getConstant('D'));
        $this->assertSame($originalRefClass->getConstant('E'), $parsedRefClass->getConstant('E'));
    }

    public function testGetReflectionConstant()
    {
        $parsedRefClass   = $this->parsedRefFileNamespace->getClass(ClassWithScalarConstants::class);
        $originalRefClass = new \ReflectionClass(ClassWithScalarConstants::class);

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
     *
     * @return array
     */
    protected function getGettersToCheck()
    {
        $allNameGetters = [
            'getStartLine', 'getEndLine', 'getDocComment', 'getExtension', 'getExtensionName',
            'getName', 'getNamespaceName', 'getShortName', 'inNamespace',
            'isAbstract', 'isCloneable', 'isFinal', 'isInstantiable',
            'isInterface', 'isInternal', 'isIterateable', 'isTrait', 'isUserDefined',
            'getConstants', 'getTraitNames', 'getInterfaceNames', 'getStaticProperties',
            'getDefaultProperties', 'getTraitAliases'
        ];

        return $allNameGetters;
    }
}
