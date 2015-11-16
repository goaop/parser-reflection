<?php
namespace ParserReflection;

use ParserReflection\Locator\ComposerLocator;

class ReflectionFunctionTest extends \PHPUnit_Framework_TestCase
{
    const STUB_FILE = '/Stub/FileWithFunctions.php';

    /**
     * @var ReflectionFile
     */
    protected $parsedRefFile;

    protected function setUp()
    {
        ReflectionEngine::init(new ComposerLocator());

        $fileName = stream_resolve_include_path(__DIR__ . self::STUB_FILE);
        $fileNode = ReflectionEngine::parseFile($fileName);

        $reflectionFile = new ReflectionFile($fileName, $fileNode);
        $this->parsedRefFile = $reflectionFile;

        include_once $fileName;
    }

    public function testGeneralInfoGetters()
    {
        $allNameGetters = [
            'getStartLine', 'getEndLine', 'getDocComment', 'getExtension', 'getExtensionName',
            'getName', 'getNamespaceName', 'getShortName', 'inNamespace', 'getStaticVariables',
            'getNumberOfParameters', 'getNumberOfRequiredParameters'
        ];

        foreach ($this->parsedRefFile->getFileNamespaces() as $fileNamespace) {
            foreach ($fileNamespace->getFunctions() as $refFunction) {
                $functionName        = $refFunction->getName();
                $originalRefFunction = new \ReflectionFunction($functionName);
                foreach ($allNameGetters as $getterName) {
                    $expectedValue = $originalRefFunction->$getterName();
                    $actualValue   = $refFunction->$getterName();
                    $this->assertSame(
                        $expectedValue,
                        $actualValue,
                        "{$getterName}() for function {$functionName} should be equal"
                    );
                }
            }
        }
    }

    public function testCoverAllMethods()
    {
        $allInternalMethods = get_class_methods(\ReflectionFunction::class);
        $allMissedMethods   = [];

        foreach ($allInternalMethods as $internalMethodName) {
            if ('export' === $internalMethodName) {
                continue;
            }
            $refMethod    = new \ReflectionMethod(ReflectionFunction::class, $internalMethodName);
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