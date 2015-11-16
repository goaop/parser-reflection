<?php
namespace ParserReflection;

use ParserReflection\Locator\ComposerLocator;
use PhpParser\Lexer;

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
            'getStartLine', 'getEndLine', 'getDocComment', 'getExtension', 'getExtensionName',
            'getName', 'getNamespaceName', 'getShortName', 'inNamespace', 'getStaticVariables',
            'isClosure', 'isDeprecated', 'isInternal', 'isUserDefined',
            'isAbstract', 'isConstructor', 'isDestructor', 'isFinal',
            'isPrivate', 'isProtected', 'isPublic', 'isStatic', '__toString'
        ];

        if (PHP_VERSION_ID >= 50600) {
            $allNameGetters[] = 'isVariadic';
            $allNameGetters[] = 'isGenerator';
        }

        $allMethods = $this->originalRefClass->getMethods();

        foreach ($allMethods as $refMethod) {
            $methodName   = $refMethod->getName();
            $parsedMethod = $this->parsedRefClass->getMethod($methodName);
            foreach ($allNameGetters as $getterName) {
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