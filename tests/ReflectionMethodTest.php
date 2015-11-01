<?php
namespace ParserReflection;

use PhpParser\Lexer;
use PhpParser\Parser;

class ReflectionMethodTest extends \PHPUnit_Framework_TestCase
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
        $allMethods = $this->originalRefClass->getMethods();
        $refClass   = new \ReflectionClass('ReflectionMethod');
        $allGetters = array();

        foreach ($refClass->getMethods() as $refMethod) {
            // let's filter only all isXXX() methods from the ReflectionMethod class
            if (substr($refMethod->getName(), 0, 2) == 'is') {
                $allGetters[] = $refMethod->getName();
            }
        }

        foreach ($allMethods as $refMethod) {
            $methodName   = $refMethod->getName();
            $parsedMethod = $this->parsedRefClass->getMethod($methodName);
            foreach ($allGetters as $getterName) {
                $expectedValue = $refMethod->$getterName();
                $actualValue   = $parsedMethod->$getterName();
                $this->assertEquals(
                    $expectedValue,
                    $actualValue,
                    "$getterName() for method $methodName should be equal"
                );
            }
        }
    }

    public function testNameGetters()
    {
        $refMethod    = $this->originalRefClass->getMethod('explicitPublicFunc');
        $parsedMethod = $this->parsedRefClass->getMethod('explicitPublicFunc');

        $this->assertEquals($refMethod->getName(), $parsedMethod->getName());
        $this->assertEquals($refMethod->getShortName(), $parsedMethod->getShortName());
    }
}