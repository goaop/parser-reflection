<?php
namespace Go\ParserReflection;

use PhpParser\Lexer;

class ReflectionPropertyTest extends \PHPUnit_Framework_TestCase
{
    const STUB_CLASS = 'Go\ParserReflection\Stub\ClassWithProperties';

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

    public function testSetAccessibleMethod()
    {
        $parsedProperty = $this->parsedRefClass->getProperty('protectedStaticProperty');
        $parsedProperty->setAccessible(true);

        $value = $parsedProperty->getValue();
        $this->assertSame('foo', $value);
    }

    public function testGetSetValueForObjectMethods()
    {
        $parsedProperty = $this->parsedRefClass->getProperty('protectedProperty');
        $parsedProperty->setAccessible(true);

        $className = self::STUB_CLASS;
        $obj       = new $className;

        $value = $parsedProperty->getValue($obj);
        $this->assertSame('a', $value);

        $parsedProperty->setValue($obj, 43);
        $value = $parsedProperty->getValue($obj);
        $this->assertSame(43, $value);
    }

    public function testCompatibilityWithOriginalConstructor()
    {
        $parsedRefProperty = new ReflectionProperty(self::STUB_CLASS, 'publicStaticProperty');
        $originalValue     = $parsedRefProperty->getValue();

        $this->assertSame(M_PI, $originalValue);
    }

    public function testDebugInfoMethod()
    {
        $parsedRefProperty   = new ReflectionProperty(self::STUB_CLASS, 'publicStaticProperty');
        $originalRefProperty = new \ReflectionProperty(self::STUB_CLASS, 'publicStaticProperty');
        $expectedValue     = (array) $originalRefProperty;
        $this->assertSame($expectedValue, $parsedRefProperty->___debugInfo());
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
            if (strpos($definerClass, 'Go\\ParserReflection') !== 0) {
                $allMissedMethods[] = $internalMethodName;
            }
        }

        if ($allMissedMethods) {
            $this->markTestIncomplete('Methods ' . join($allMissedMethods, ', ') . ' are not implemented');
        }
    }
}
