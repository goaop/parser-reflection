<?php
namespace ParserReflection;

use PhpParser\Lexer;
use PhpParser\Parser;

class ReflectionClassTest extends \PHPUnit_Framework_TestCase
{
    const STUB_FILE = './Stub/FileWithClasses.php';

    /**
     * @var ReflectionFileNamespace
     */
    protected $parsedRefFileNamespace;

    protected function setUp()
    {
        $parser = new Parser(new Lexer(array(
            'comments', 'startLine', 'endLine', 'startTokenPos', 'endTokenPos', 'startFilePos', 'endFilePos'
        )));
        $fileName       = stream_resolve_include_path(__DIR__ . self::STUB_FILE);
        $fileNode       = $parser->parse(file_get_contents($fileName));
        $reflectionFile = new ReflectionFile($fileName, $fileNode);

        $parsedFileNamespace          = $reflectionFile->getFileNamespace('ParserReflection\Stub');
        $this->parsedRefFileNamespace = $parsedFileNamespace;

        include_once $fileName;
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
            $notRequiresParams = $refMethod->getNumberOfRequiredParameters() == 0;
            if (substr($refMethod->getName(), 0, 2) == 'is' && $notRequiresParams && $refMethod->isPublic()) {
                $allGetters[] = $refMethod->getName();
            }
        }

        foreach ($this->parsedRefFileNamespace->getClasses() as $parsedRefClass) {
            $originalRefClass = new \ReflectionClass($parsedRefClass->getName());
            foreach ($allGetters as $getterName) {
                $expectedValue = $originalRefClass->$getterName();
                $actualValue   = $parsedRefClass->$getterName();
                $this->assertEquals(
                    $expectedValue,
                    $actualValue,
                    "$getterName() for class should be equal"
                );
            }
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