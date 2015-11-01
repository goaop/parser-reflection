<?php
namespace ParserReflection;

use PhpParser\Lexer;
use PhpParser\Parser;

class ReflectionClassTest extends \PHPUnit_Framework_TestCase
{
    const STUB_CLASS = '\ParserReflection\Stub\AbstractClassWithMethods';

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

        $file   = $refClass->getFileName();
        $parser = new Parser(new Lexer(array(
            'comments', 'startLine', 'endLine', 'startTokenPos', 'endTokenPos', 'startFilePos', 'endFilePos'
        )));

        $fileNode       = $parser->parse(file_get_contents($file));
        $reflectionFile = new ReflectionFile($file, $fileNode);

        $parsedClass = $reflectionFile->getFileNamespace($refClass->getNamespaceName())->getClass($refClass->getShortName());
        $this->parsedRefClass = $parsedClass;
    }

    /**
     * This test case checks all isXXX() methods reflection for parsed item with internal one
     */
    public function testModifiersAreEqual()
    {
        $refClass   = new \ReflectionClass('ReflectionClass');
        $allGetters = array();

        foreach ($refClass->getMethods() as $refMethod) {
            // let's filter only all isXXX() methods from the ReflectionMethod class
            if (substr($refMethod->getName(), 0, 2) == 'is' && $refMethod->getNumberOfRequiredParameters() == 0) {
                $allGetters[] = $refMethod->getName();
            }
        }

        foreach ($allGetters as $getterName) {
            $expectedValue = $this->originalRefClass->$getterName();
            $actualValue   = $this->parsedRefClass->$getterName();
            $this->assertEquals(
                $expectedValue,
                $actualValue,
                "$getterName() for class should be equal"
            );
        }
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