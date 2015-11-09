<?php
namespace ParserReflection;

use ParserReflection\Locator\ComposerLocator;
use ParserReflection\Stub\ImplicitAbstractClass;
use ParserReflection\Stub\SimpleAbstractInheritance;

class ReflectionClassTest extends \PHPUnit_Framework_TestCase
{
    const STUB_FILE = '/Stub/FileWithClasses.php';

    /**
     * @var ReflectionFileNamespace
     */
    protected $parsedRefFileNamespace;

    protected function setUp()
    {
        ReflectionEngine::init(new ComposerLocator());

        $fileName = stream_resolve_include_path(__DIR__ . self::STUB_FILE);
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
        $allNameGetters = [
            'getStartLine', 'getEndLine', 'getDocComment', 'getExtension', 'getExtensionName',
            'getName', 'getNamespaceName', 'getShortName', 'inNamespace',
            'isAbstract', 'isCloneable', 'isFinal', 'isInstantiable',
            'isInterface', 'isInternal', 'isIterateable', 'isTrait', 'isUserDefined',
            'getConstants'
        ];
        foreach ($this->parsedRefFileNamespace->getClasses() as $parsedRefClass) {
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
}