<?php
namespace Go\ParserReflection;

use Go\ParserReflection\Stub\AbstractInterface;
use Go\ParserReflection\Stub\ClassWithConstantsAndInheritance;
use Go\ParserReflection\Stub\ClassWithInterface;
use Go\ParserReflection\Stub\ClassWithMagicConstants;
use Go\ParserReflection\Stub\ClassWithMethodsAndProperties;
use Go\ParserReflection\Stub\ClassWithScalarConstants;
use Go\ParserReflection\Stub\ClassWithTrait;
use Go\ParserReflection\Stub\ClassWithTraitAndAdaptation;
use Go\ParserReflection\Stub\ClassWithTraitAndConflict;
use Go\ParserReflection\Stub\ClassWithTraitAndInterface;
use Go\ParserReflection\Stub\ExplicitAbstractClass;
use Go\ParserReflection\Stub\FinalClass;
use Go\ParserReflection\Stub\ImplicitAbstractClass;
use Go\ParserReflection\Stub\InterfaceWithMethod;
use Go\ParserReflection\Stub\NoCloneable;
use Go\ParserReflection\Stub\NoInstantiable;
use Go\ParserReflection\Stub\SimpleAbstractInheritance;
use Go\ParserReflection\Stub\SimpleInheritance;
use Go\ParserReflection\Stub\SimpleInterface;
use Go\ParserReflection\Stub\SimpleTrait;
use Go\ParserReflection\Stub\TraitWithProperties;

class ReflectionClassTest extends \PHPUnit_Framework_TestCase
{
    const STUB_FILE55 = '/Stub/FileWithClasses55.php';
    const STUB_FILE56 = '/Stub/FileWithClasses56.php';

    /**
     * @var ReflectionFileNamespace
     */
    protected $parsedRefFileNamespace;

    protected function setUp()
    {
        $fileName = stream_resolve_include_path(__DIR__ . self::STUB_FILE55);
        $fileNode = ReflectionEngine::parseFile($fileName);

        $reflectionFile = new ReflectionFile($fileName, $fileNode);

        $parsedFileNamespace          = $reflectionFile->getFileNamespace('Go\ParserReflection\Stub');
        $this->parsedRefFileNamespace = $parsedFileNamespace;

        include_once $fileName;
    }

    /**
     * Tests that names are correct for reflection data
     *
     * @dataProvider listOfClasses55
     *
     * @param string $className Class name to test
     */
    public function testGeneralInfoGetters($className)
    {
        $parsedRefClass = $this->parsedRefFileNamespace->getClass($className);
        $this->performGeneralMethodComparison($parsedRefClass);
    }

    /**
     * Tests getModifier() method
     * NB: value is masked because there are many internal constants that aren't exported in the userland
     *
     * @dataProvider listOfClasses55
     *
     * @param string $className Class name to test
     */
    public function testGetModifiers($className)
    {
        $mask =
            \ReflectionClass::IS_EXPLICIT_ABSTRACT
            + \ReflectionClass::IS_FINAL
            + \ReflectionClass::IS_IMPLICIT_ABSTRACT;

        $parsedRefClass   = $this->parsedRefFileNamespace->getClass($className);
        $originalRefClass = new \ReflectionClass($className);

        $parsedModifiers   = $parsedRefClass->getModifiers() & $mask;
        $originalModifiers = $originalRefClass->getModifiers() & $mask;

        $this->assertEquals($originalModifiers, $parsedModifiers);
    }

    /**
     * Tests getMethods() returns correct number of methods for the class
     *
     * @dataProvider listOfClasses55
     *
     * @param string $className Class name to test
     */
    public function testGetMethods($className)
    {
        $parsedRefClass   = $this->parsedRefFileNamespace->getClass($className);
        $originalRefClass = new \ReflectionClass($className);

        $parsedMethods   = $parsedRefClass->getMethods();
        $originalMethods = $originalRefClass->getMethods();
        if ($parsedRefClass->getTraitAliases()) {
            $this->markTestIncomplete("Adoptation methods for traits are not supported yet");
        }
        $this->assertCount(count($originalMethods), $parsedMethods);
    }

    /**
     * Tests getProperties() returns correct number of properties for the class
     *
     * @dataProvider listOfClasses55
     *
     * @param string $className Class name to test
     */
    public function testGetProperties($className)
    {
        $parsedRefClass   = $this->parsedRefFileNamespace->getClass($className);
        $originalRefClass = new \ReflectionClass($className);

        $parsedProperties   = $parsedRefClass->getProperties();
        $originalProperties = $originalRefClass->getProperties();

        $this->assertCount(count($originalProperties), $parsedProperties);
    }

    /**
     * Data provider with list of all class names to test for PHP5.5 and upper
     *
     * @return array
     */
    public function listOfClasses55()
    {
        $classNames = [
            'Go\ParserReflection\Stub\ExplicitAbstractClass'            => ['Go\ParserReflection\Stub\ExplicitAbstractClass'],
            'Go\ParserReflection\Stub\ImplicitAbstractClass'            => ['Go\ParserReflection\Stub\ImplicitAbstractClass'],
            'Go\ParserReflection\Stub\FinalClass'                       => ['Go\ParserReflection\Stub\FinalClass'],
            'Go\ParserReflection\Stub\ClassWithMethodsAndProperties'    => ['Go\ParserReflection\Stub\ClassWithMethodsAndProperties'],
            'Go\ParserReflection\Stub\SimpleInterface'                  => ['Go\ParserReflection\Stub\SimpleInterface'],
            'Go\ParserReflection\Stub\InterfaceWithMethod'              => ['Go\ParserReflection\Stub\InterfaceWithMethod'],
            'Go\ParserReflection\Stub\SimpleTrait'                      => ['Go\ParserReflection\Stub\SimpleTrait'],
            'Go\ParserReflection\Stub\SimpleInheritance'                => ['Go\ParserReflection\Stub\SimpleInheritance'],
            'Go\ParserReflection\Stub\SimpleAbstractInheritance'        => ['Go\ParserReflection\Stub\SimpleAbstractInheritance'],
            'Go\ParserReflection\Stub\ClassWithInterface'               => ['Go\ParserReflection\Stub\ClassWithInterface'],
            'Go\ParserReflection\Stub\ClassWithTrait'                   => ['Go\ParserReflection\Stub\ClassWithTrait'],
            'Go\ParserReflection\Stub\ClassWithTraitAndInterface'       => ['Go\ParserReflection\Stub\ClassWithTraitAndInterface'],
            'Go\ParserReflection\Stub\NoCloneable'                      => ['Go\ParserReflection\Stub\NoCloneable'],
            'Go\ParserReflection\Stub\NoInstantiable'                   => ['Go\ParserReflection\Stub\NoInstantiable'],
            'Go\ParserReflection\Stub\AbstractInterface'                => ['Go\ParserReflection\Stub\AbstractInterface'],
            'Go\ParserReflection\Stub\ClassWithScalarConstants'         => ['Go\ParserReflection\Stub\ClassWithScalarConstants'],
            'Go\ParserReflection\Stub\ClassWithMagicConstants'          => ['Go\ParserReflection\Stub\ClassWithMagicConstants'],
            'Go\ParserReflection\Stub\ClassWithConstantsAndInheritance' => ['Go\ParserReflection\Stub\ClassWithConstantsAndInheritance'],
            'Go\ParserReflection\Stub\TraitWithProperties'              => ['Go\ParserReflection\Stub\TraitWithProperties'],
            'Go\ParserReflection\Stub\ClassWithTraitAndAdaptation'      => ['Go\ParserReflection\Stub\ClassWithTraitAndAdaptation'],
            'Go\ParserReflection\Stub\ClassWithTraitAndConflict'        => ['Go\ParserReflection\Stub\ClassWithTraitAndConflict'],
        ];

        return $classNames;
    }

    /**
     * Tests specific features of PHP5.6 and newer, for example, array constants, etc
     */
    public function testGettersPHP56()
    {
        if (PHP_VERSION_ID < 50600) {
            $this->markTestSkipped("Can not test new features on old version of PHP");
        }

        $fileName       = stream_resolve_include_path(__DIR__ . self::STUB_FILE56);
        $fileNode       = ReflectionEngine::parseFile($fileName);
        $reflectionFile = new ReflectionFile($fileName, $fileNode);

        include_once $fileName;

        $parsedFileNamespace = $reflectionFile->getFileNamespace('Go\ParserReflection\Stub');
        foreach ($parsedFileNamespace->getClasses() as $parsedRefClass) {
            $this->performGeneralMethodComparison($parsedRefClass);
        }
    }

    public function testNewInstanceMethod()
    {
        $parsedRefClass = $this->parsedRefFileNamespace->getClass('Go\ParserReflection\Stub\FinalClass');
        $instance = $parsedRefClass->newInstance();
        $this->assertInstanceOf('Go\ParserReflection\Stub\FinalClass', $instance);
        $this->assertSame([], $instance->args);
    }

    public function testNewInstanceArgsMethod()
    {
        $someValueByRef = 5;
        $arguments      = [1, &$someValueByRef];
        $parsedRefClass = $this->parsedRefFileNamespace->getClass('Go\ParserReflection\Stub\FinalClass');
        $instance       = $parsedRefClass->newInstanceArgs($arguments);
        $this->assertInstanceOf('Go\ParserReflection\Stub\FinalClass', $instance);
        $this->assertSame($arguments, $instance->args);
    }

    public function testNewInstanceWithoutConstructorMethod()
    {
        $arguments      = [1, 2];
        $parsedRefClass = $this->parsedRefFileNamespace->getClass('Go\ParserReflection\Stub\FinalClass');
        $instance       = $parsedRefClass->newInstanceWithoutConstructor($arguments);
        $this->assertInstanceOf('Go\ParserReflection\Stub\FinalClass', $instance);
        $this->assertSame([], $instance->args);
    }

    public function testSetStaticPropertyValueMethod()
    {
        $parsedRefClass = $this->parsedRefFileNamespace->getClass('Go\ParserReflection\Stub\ClassWithConstantsAndInheritance');
        $originalRefClass = new \ReflectionClass('Go\ParserReflection\Stub\ClassWithConstantsAndInheritance');

        $parsedRefClass->setStaticPropertyValue('h', 'test');
        $this->assertSame($parsedRefClass->getStaticPropertyValue('h'), $originalRefClass->getStaticPropertyValue('h'));
    }

    public function testGetMethodsFiltering()
    {
        $parsedRefClass   = $this->parsedRefFileNamespace->getClass('Go\ParserReflection\Stub\ClassWithMethodsAndProperties');
        $originalRefClass = new \ReflectionClass('Go\ParserReflection\Stub\ClassWithMethodsAndProperties');

        $parsedMethods   = $parsedRefClass->getMethods(\ReflectionMethod::IS_PUBLIC);
        $originalMethods = $originalRefClass->getMethods(\ReflectionMethod::IS_PUBLIC);

        $this->assertCount(count($originalMethods), $parsedMethods);

        $parsedMethods   = $parsedRefClass->getMethods(\ReflectionMethod::IS_PRIVATE | \ReflectionMethod::IS_STATIC);
        $originalMethods = $originalRefClass->getMethods(\ReflectionMethod::IS_PRIVATE | \ReflectionMethod::IS_STATIC);

        $this->assertCount(count($originalMethods), $parsedMethods);
    }

    public function testDirectMethods()
    {
        $parsedRefClass   = $this->parsedRefFileNamespace->getClass('Go\ParserReflection\Stub\ImplicitAbstractClass');
        $originalRefClass = new \ReflectionClass('Go\ParserReflection\Stub\ImplicitAbstractClass');

        $this->assertEquals($originalRefClass->hasMethod('test'), $parsedRefClass->hasMethod('test'));
        $this->assertCount(count($originalRefClass->getMethods()), $parsedRefClass->getMethods());

        $originalMethodName = $originalRefClass->getMethod('test')->getName();
        $parsedMethodName   = $parsedRefClass->getMethod('test')->getName();
        $this->assertSame($originalMethodName, $parsedMethodName);
    }

    public function testInheritedMethods()
    {
        $parsedRefClass   = $this->parsedRefFileNamespace->getClass('Go\ParserReflection\Stub\SimpleAbstractInheritance');
        $originalRefClass = new \ReflectionClass('Go\ParserReflection\Stub\SimpleAbstractInheritance');

        $this->assertEquals($originalRefClass->hasMethod('test'), $parsedRefClass->hasMethod('test'));
    }


    public function testCoverAllMethods()
    {
        $allInternalMethods = get_class_methods('ReflectionClass');
        $allMissedMethods   = [];

        foreach ($allInternalMethods as $internalMethodName) {
            if ('export' === $internalMethodName) {
                continue;
            }
            $refMethod    = new \ReflectionMethod('Go\ParserReflection\ReflectionClass', $internalMethodName);
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
    protected function performGeneralMethodComparison(ReflectionCLass $parsedRefClass, array $allNameGetters = [])
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
}
