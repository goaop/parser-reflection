<?php
namespace ParserReflection;

use ParserReflection\Locator\ComposerLocator;
use PhpParser\Lexer;

class ReflectionPropertyTest extends \PHPUnit_Framework_TestCase
{
    const STUB_CLASS = '\ParserReflection\Stub\AbstractClassWithProperties';

    /**
     * @var \ReflectionClass
     */
    protected $originalRefClass;

    /**
     * @var ReflectionClass
     */
    protected $parsedRefClass;

    protected function setUp()
    {
        $this->originalRefClass = $refClass = new \ReflectionClass(self::STUB_CLASS);

        $fileName = $refClass->getFileName();
        ReflectionEngine::init(new ComposerLocator());

        $fileNode       = ReflectionEngine::parseFile($fileName);
        $reflectionFile = new ReflectionFile($fileName, $fileNode);

        $parsedClass = $reflectionFile->getFileNamespace($refClass->getNamespaceName())->getClass($refClass->getName());
        $this->parsedRefClass = $parsedClass;
    }

    public function testGeneralInfoGetters()
    {
        $allNameGetters = [
            'isDefault', 'getName', 'getModifiers', 'getDocComment',
            'isPrivate', 'isProtected', 'isPublic', 'isStatic', '__toString'
        ];

        $allProperties  = $this->originalRefClass->getProperties();

        foreach ($allProperties as $refProperty) {
            $propertyName   = $refProperty->getName();
            $parsedProperty = $this->parsedRefClass->getProperty($propertyName);
            foreach ($allNameGetters as $getterName) {
                $expectedValue = $refProperty->$getterName();
                $actualValue   = $parsedProperty->$getterName();
                $this->assertSame(
                    $expectedValue,
                    $actualValue,
                    "$getterName() for property $propertyName should be equal"
                );
            }
        }
    }

    public function testCoverAllMethods()
    {
        $allInternalMethods = get_class_methods(\ReflectionProperty::class);
        $allMissedMethods   = [];

        foreach ($allInternalMethods as $internalMethodName) {
            if ('export' === $internalMethodName) {
                continue;
            }
            $refMethod    = new \ReflectionMethod(ReflectionProperty::class, $internalMethodName);
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