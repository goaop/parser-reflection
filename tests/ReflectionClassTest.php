<?php
namespace Go\ParserReflection;

use Go\ParserReflection\Stub\ClassWithConstantsAndInheritance;
use Go\ParserReflection\Stub\ClassWithMethodsAndProperties;
use Go\ParserReflection\Stub\ClassWithScalarConstants;
use Go\ParserReflection\Stub\FinalClass;
use Go\ParserReflection\Stub\ImplicitAbstractClass;
use Go\ParserReflection\Stub\SimpleAbstractInheritance;

class ReflectionClassTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var ReflectionFileNamespace
     */
    protected $parsedRefFileNamespace;

    protected function setUp()
    {
        $this->setUpFile(__DIR__ . '/Stub/FileWithClasses55.php');
    }

    /**
     * Tests that names are correct for reflection data
     *
     * @dataProvider fileProvider
     *
     * @param string $fileName File name to test
     */
    public function testGeneralInfoGetters($fileName)
    {
        $this->setUpFile($fileName);
        $parsedClasses = $this->parsedRefFileNamespace->getClasses();
        foreach ($parsedClasses as $parsedRefClass) {
            $this->performGeneralMethodComparison($parsedRefClass);
        }
    }

    /**
     * Tests getModifier() method
     * NB: value is masked because there are many internal constants that aren't exported in the userland
     *
     * @dataProvider fileProvider
     *
     * @param string $fileName File name to test
     */
    public function testGetModifiers($fileName)
    {
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
     * Tests getMethods() returns correct number of methods for the class
     *
     * @dataProvider fileProvider
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
     * Tests getProperties() returns correct number of properties for the class
     *
     * @dataProvider fileProvider
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

    /**
     * Provides a list of files for analysis
     *
     * @return array
     */
    public function fileProvider()
    {
        $files = ['PHP5.5' => [__DIR__ . '/Stub/FileWithClasses55.php']];

        if (PHP_VERSION_ID >= 50600) {
            $files['PHP5.6'] = [__DIR__ . '/Stub/FileWithClasses56.php'];
        }
        if (PHP_VERSION_ID >= 70000) {
            $files['PHP7.0'] = [__DIR__ . '/Stub/FileWithClasses70.php'];
        }

        return $files;
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
        $parsedRefClass   = $this->parsedRefFileNamespace->getClass(SimpleAbstractInheritance::class);
        $originalRefClass = new \ReflectionClass(SimpleAbstractInheritance::class);

        $this->assertEquals($originalRefClass->hasMethod('test'), $parsedRefClass->hasMethod('test'));
    }


    public function testCoverAllMethods()
    {
        $allInternalMethods = get_class_methods(\ReflectionClass::class);
        $allMissedMethods   = [];

        foreach ($allInternalMethods as $internalMethodName) {
            if ('export' === $internalMethodName) {
                continue;
            }
            $refMethod    = new \ReflectionMethod(ReflectionClass::class, $internalMethodName);
            $definerClass = $refMethod->getDeclaringClass()->getName();
            if (strpos($definerClass, 'Go\\ParserReflection') !== 0) {
                $allMissedMethods[] = $internalMethodName;
            }
        }

        if ($allMissedMethods) {
            $this->markTestIncomplete('Methods ' . join($allMissedMethods, ', ') . ' are not implemented');
        }
    }

    /**
     * Performs list of common checks on parsed and runtime refelection
     *
     * @param ReflectionCLass $parsedRefClass
     * @param array $allNameGetters Optional list of getters to check
     */
    protected function performGeneralMethodComparison(ReflectionClass $parsedRefClass, array $allNameGetters = [])
    {
        $allNameGetters = $allNameGetters ?: [
            'getStartLine', 'getEndLine', 'getDocComment', 'getExtension', 'getExtensionName',
            'getName', 'getNamespaceName', 'getShortName', 'inNamespace',
            'isAbstract', 'isCloneable', 'isFinal', 'isInstantiable',
            'isInterface', 'isInternal', 'isIterateable', 'isTrait', 'isUserDefined',
            'getConstants', 'getTraitNames', 'getInterfaceNames', 'getStaticProperties',
            'getDefaultProperties', 'getTraitAliases'
        ];

        $className        = $parsedRefClass->getName();
        $originalRefClass = new \ReflectionClass($className);
        foreach ($allNameGetters as $getterName) {
            $expectedValue = $originalRefClass->$getterName();
            $actualValue   = $parsedRefClass->$getterName();
            $this->assertSame(
                $expectedValue,
                $actualValue,
                "$getterName() for class $className should be equal"
            );
        }
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
     * Setups file for parsing
     *
     * @param string $fileName File to use
     */
    private function setUpFile($fileName)
    {
        $fileName = stream_resolve_include_path($fileName);
        $fileNode = ReflectionEngine::parseFile($fileName);

        $reflectionFile = new ReflectionFile($fileName, $fileNode);

        $parsedFileNamespace          = $reflectionFile->getFileNamespace('Go\ParserReflection\Stub');
        $this->parsedRefFileNamespace = $parsedFileNamespace;

        include_once $fileName;
    }
}
