<?php
namespace Go\ParserReflection;

use Go\ParserReflection\Stub\ClassWithConstantsAndInheritance;
use Go\ParserReflection\Stub\ClassWithMethodsAndProperties;
use Go\ParserReflection\Stub\ClassWithScalarConstants;
use Go\ParserReflection\Stub\FinalClass;
use Go\ParserReflection\Stub\ImplicitAbstractClass;
use Go\ParserReflection\Stub\SimpleAbstractInheritance;

class ReflectionClassTest extends AbstractClassTestCaseBase
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
     * @dataProvider getClassesToAnalyze
     *
     * @param string $fileName File name to test
     */
    public function testGetModifiers($class, $fileName)
    {
        $mask =
            \ReflectionClass::IS_EXPLICIT_ABSTRACT
            + \ReflectionClass::IS_FINAL
            + \ReflectionClass::IS_IMPLICIT_ABSTRACT;

        if ($fileName) {
            $this->setUpFile($fileName);
            $parsedRefClass = $this->parsedRefFileNamespace->getClass($class);
        } else {
            $this->lastFileSetUp          = null;
            $this->parsedRefFileNamespace = null;
            $this->parsedRefClass         = null;
            $parsedRefClass               = new ReflectionClass($class);
        }
        $originalRefClass  = new \ReflectionClass($parsedRefClass->getName());
        $parsedModifiers   = $parsedRefClass->getModifiers() & $mask;
        $originalModifiers = $originalRefClass->getModifiers() & $mask;

        $this->assertEquals($originalModifiers, $parsedModifiers);
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
        $this->assertReflectorValueSame(
            $expectedValue,
            $actualValue,
            get_class($parsedClass) . "->$getterName() for method $className->$methodName() should be equal\nexpected: " . $this->getStringificationOf($expectedValue) . "\nactual: " . $this->getStringificationOf($actualValue)
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
        $classes   = $this->getClassesToAnalyze();
        foreach ($classes as $testCaseDesc => $classFilePair) {
            if ($classFilePair['fileName']) {
                $fileNode       = ReflectionEngine::parseFile($classFilePair['fileName']);
                $reflectionFile = new ReflectionFile($classFilePair['fileName'], $fileNode);
                $namespace      = $this->getNamespaceFromName($classFilePair['class']);
                $fileNamespace  = $reflectionFile->getFileNamespace($namespace);
                $parsedClass    = $fileNamespace->getClass($classFilePair['class']);
                include_once $classFilePair['fileName'];
            } else {
                $parsedClass    = new ReflectionClass($classFilePair['class']);
            }
            foreach ($allNameGetters as $getterName) {
                $testCases[$testCaseDesc . ', ' . $getterName] = [
                    $parsedClass,
                    $getterName
                ];
            }
        }

        return $testCases;
    }

    /**
     * Tests getMethods() returns correct number of methods for the class
     *
     * @dataProvider getClassesToAnalyze
     *
     * @param string $fileName File name to test
     */
    public function testGetMethodCount($class, $fileName)
    {
        if ($fileName) {
            $this->setUpFile($fileName);
            $parsedRefClass = $this->parsedRefFileNamespace->getClass($class);
        } else {
            $this->lastFileSetUp          = null;
            $this->parsedRefFileNamespace = null;
            $this->parsedRefClass         = null;
            $parsedRefClass               = new ReflectionClass($class);
        }

        $originalRefClass  = new \ReflectionClass($parsedRefClass->getName());
        $parsedMethods     = $parsedRefClass->getMethods();
        $originalMethods   = $originalRefClass->getMethods();
        if ($parsedRefClass->getTraitAliases()) {
            $this->markTestIncomplete("Adoptation methods for traits are not supported yet");
        }
        $this->assertCount(count($originalMethods), $parsedMethods);
    }

    /**
     * Tests getProperties() returns correct number of properties for the class
     *
     * @dataProvider getClassesToAnalyze
     *
     * @param string $fileName File name to test
     */
    public function testGetProperties($class, $fileName)
    {
        if ($fileName) {
            $this->setUpFile($fileName);
            $parsedRefClass = $this->parsedRefFileNamespace->getClass($class);
        } else {
            $this->lastFileSetUp          = null;
            $this->parsedRefFileNamespace = null;
            $this->parsedRefClass         = null;
            $parsedRefClass               = new ReflectionClass($class);
        }
        $originalRefClass   = new \ReflectionClass($parsedRefClass->getName());
        $parsedProperties   = $parsedRefClass->getProperties();
        $originalProperties = $originalRefClass->getProperties();

        $this->assertCount(count($originalProperties), $parsedProperties);
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
        $parsedRefClass = $this->parsedRefFileNamespace->getClass(ClassWithConstantsAndInheritance::class);
        $originalRefClass = new \ReflectionClass(ClassWithConstantsAndInheritance::class);

        $parsedRefClass->setStaticPropertyValue('h', 'test');
        $this->assertSame($parsedRefClass->getStaticPropertyValue('h'), $originalRefClass->getStaticPropertyValue('h'));
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
