<?php
namespace ParserReflection;

use ParserReflection\Locator\ComposerLocator;
use ParserReflection\Stub\FinalClass;
use ParserReflection\Stub\ImplicitAbstractClass;
use ParserReflection\Stub\SimpleAbstractInheritance;

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
        ReflectionEngine::init(new ComposerLocator());

        $fileName = stream_resolve_include_path(__DIR__ . self::STUB_FILE55);
        $fileNode = ReflectionEngine::parseFile($fileName);

        $reflectionFile = new ReflectionFile($fileName, $fileNode);

        $parsedFileNamespace          = $reflectionFile->getFileNamespace('ParserReflection\Stub');
        $this->parsedRefFileNamespace = $parsedFileNamespace;

        include_once $fileName;
    }

    /**
     * Tests that names are correct for reflection data
     */
    public function testGeneralInfoGetters()
    {
        foreach ($this->parsedRefFileNamespace->getClasses() as $parsedRefClass) {
            $this->performGeneralMethodComparison($parsedRefClass);
        }
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

        $parsedFileNamespace = $reflectionFile->getFileNamespace('ParserReflection\Stub');
        foreach ($parsedFileNamespace->getClasses() as $parsedRefClass) {
            $this->performGeneralMethodComparison($parsedRefClass);
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
        $arguments      = [1, 2];
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
            $refMethod    = new \ReflectionMethod(ReflectionClass::class, $internalMethodName);
            $definerClass = $refMethod->getDeclaringClass()->getName();
            if (strpos($definerClass, 'ParserReflection') !== 0) {
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
        $allNameGetters ?: [
            'getStartLine', 'getEndLine', 'getDocComment', 'getExtension', 'getExtensionName',
            'getName', 'getNamespaceName', 'getShortName', 'inNamespace',
            'isAbstract', 'isCloneable', 'isFinal', 'isInstantiable',
            'isInterface', 'isInternal', 'isIterateable', 'isTrait', 'isUserDefined',
            'getConstants'
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