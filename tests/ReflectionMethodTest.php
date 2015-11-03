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
        $parser = new Parser(new Lexer(['usedAttributes' => [
            'comments', 'startLine', 'endLine', 'startTokenPos', 'endTokenPos', 'startFilePos', 'endFilePos'
        ]]));

        $fileNode       = $parser->parse(file_get_contents($file));
        $reflectionFile = new ReflectionFile($file, $fileNode);

        $parsedClass = $reflectionFile->getFileNamespace($refClass->getNamespaceName())->getClass($refClass->getName());
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
            if (substr($refMethod->getName(), 0, 2) == 'is' && $refMethod->isPublic()) {
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
        $allNameGetters = ['getName', 'getNamespaceName', 'getShortName', 'inNamespace'];

        $refMethod      = $this->originalRefClass->getMethod('explicitPublicFunc');
        $parsedMethod   = $this->parsedRefClass->getMethod('explicitPublicFunc');

        foreach ($allNameGetters as $getterName) {
            $expectedValue = $refMethod->$getterName();
            $actualValue   = $parsedMethod->$getterName();
            $this->assertEquals(
                $expectedValue,
                $actualValue,
                "$getterName() for method should be equal"
            );
        }
    }

    public function testGeneralInfoGetters()
    {
        $allNameGetters = ['getStartLine', 'getEndLine', 'getDocComment', 'getExtension', 'getExtensionName'];

        $refMethod      = $this->originalRefClass->getMethod('funcWithDocAndBody');
        $parsedMethod   = $this->parsedRefClass->getMethod('funcWithDocAndBody');

        foreach ($allNameGetters as $getterName) {
            $expectedValue = $refMethod->$getterName();
            $actualValue   = $parsedMethod->$getterName();
            $this->assertEquals(
                $expectedValue,
                $actualValue,
                "$getterName() for method should be equal"
            );
        }
    }

    public function testGetStaticVariables()
    {
        $refMethod    = $this->originalRefClass->getMethod('funcWithDocAndBody');
        $parsedMethod = $this->parsedRefClass->getMethod('funcWithDocAndBody');

        $originalVariables = $refMethod->getStaticVariables();
        $parsedVariables   = $parsedMethod->getStaticVariables();

        $this->assertEquals($originalVariables, $parsedVariables);
    }

    public function testCoverAllMethods()
    {
        $allInternalMethods = get_class_methods(\ReflectionMethod::class);
        $allMissedMethods   = [];

        foreach ($allInternalMethods as $internalMethodName) {
            $refMethod    = new \ReflectionMethod(ReflectionMethod::class, $internalMethodName);
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