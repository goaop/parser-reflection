<?php
declare(strict_types=1);

namespace Go\ParserReflection;

use Go\ParserReflection\Stub\Foo;
use Go\ParserReflection\Stub\SubFoo;
use TestParametersForRootNsClass;
use Go\ParserReflection\Stub\ClassWithPhp71Features;

class ReflectionClassConstantTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var ReflectionFile
     */
    protected $parsedRefFile;

    protected function setUp()
    {
        $this->setUpFile(__DIR__ . '/Stub/FileWithClasses71.php');
    }

    public function testGeneralInfoGetters()
    {
        $allNameGetters = [
            'getDocComment',
            'getModifiers',
            'getName',
            'getValue',
            'isPrivate',
            'isProtected',
            'isPublic',
            '__toString'
        ];

        foreach ($this->parsedRefFile->getFileNamespaces() as $fileNamespace) {
            foreach ($fileNamespace->getClasses() as $refClass) {
                $className = $refClass->getName();
                foreach ($refClass->getReflectionConstants() as $refReflectionConstant) {
                    $classConstantName = $refReflectionConstant->getName();
                    $originalRefParameter = new \ReflectionClassConstant($className, $classConstantName);
                    foreach ($allNameGetters as $getterName) {
                        $expectedValue = $originalRefParameter->$getterName();
                        $actualValue = $refReflectionConstant->$getterName();
                        $this->assertSame(
                            $expectedValue,
                            $actualValue,
                            "{$getterName}() for parameter {$className}::{$classConstantName} should be equal"
                        );
                    }
                }
            }
        }
    }

    public function testGetClassConstantProperties()
    {
        $parsedNamespace = $this->parsedRefFile->getFileNamespace('Go\ParserReflection\Stub');
        $parsedClass = $parsedNamespace->getClass(ClassWithPhp71Features::class);

        $constant = $parsedClass->getReflectionConstant('PUBLIC_CONST_A');
        $this->assertSame('PUBLIC_CONST_A', $constant->name);
        $this->assertSame(ClassWithPhp71Features::class, $constant->class);
    }

    public function testGetClassConstant()
    {
        $parsedNamespace = $this->parsedRefFile->getFileNamespace('Go\ParserReflection\Stub');
        $parsedClass = $parsedNamespace->getClass(ClassWithPhp71Features::class);

        $classConstants = $parsedClass->getReflectionConstants();
        $this->assertSame($classConstants[0], $parsedClass->getReflectionConstant('PUBLIC_CONST_A'));
        $this->assertSame($classConstants[1], $parsedClass->getReflectionConstant('PUBLIC_CONST_B'));
        $this->assertSame($classConstants[2], $parsedClass->getReflectionConstant('PROTECTED_CONST'));
        $this->assertSame($classConstants[3], $parsedClass->getReflectionConstant('PRIVATE_CONST'));
        $this->assertSame($classConstants[4], $parsedClass->getReflectionConstant('CALCULATED_CONST'));
    }

    public function testCoverAllMethods()
    {
        $allInternalMethods = get_class_methods(\ReflectionClassConstant::class);
        $allMissedMethods = [];

        foreach ($allInternalMethods as $internalMethodName) {
            if ('export' === $internalMethodName) {
                continue;
            }
            $refMethod = new \ReflectionMethod(ReflectionClassConstant::class, $internalMethodName);
            $definerClass = $refMethod->getDeclaringClass()->getName();
            if (strpos($definerClass, 'Go\\ParserReflection') !== 0) {
                $allMissedMethods[] = $internalMethodName;
            }
        }

        if ($allMissedMethods) {
            $this->markTestIncomplete('Methods ' . implode($allMissedMethods, ', ') . ' are not implemented');
        }
    }

    /**
     * Setups file for parsing
     *
     * @param string $fileName File name to use
     */
    private function setUpFile($fileName)
    {
        $fileName = stream_resolve_include_path($fileName);
        $fileNode = ReflectionEngine::parseFile($fileName);

        $reflectionFile = new ReflectionFile($fileName, $fileNode);
        $this->parsedRefFile = $reflectionFile;

        include_once $fileName;
    }
}
