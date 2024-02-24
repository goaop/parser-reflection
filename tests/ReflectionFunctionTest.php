<?php
declare(strict_types=1);

namespace Go\ParserReflection;

use PHPUnit\Framework\TestCase;

class ReflectionFunctionTest extends TestCase
{
    public const STUB_FILE55 = '/Stub/FileWithFunctions55.php';
    public const STUB_FILE70 = '/Stub/FileWithFunctions70.php';

    /**
     * @var ReflectionFile
     */
    protected $parsedRefFile;

    protected function setUp(): void
    {
        $fileName = stream_resolve_include_path(__DIR__ . self::STUB_FILE55);

        $reflectionFile = new ReflectionFile($fileName);
        $this->parsedRefFile = $reflectionFile;

        include_once $fileName;
    }

    public function testGeneralInfoGetters(): void
    {
        $allNameGetters = [
            'getStartLine', 'getEndLine', 'getDocComment', 'getExtension', 'getExtensionName',
            'getName', 'getNamespaceName', 'getShortName', 'inNamespace', 'getStaticVariables',
            'getNumberOfParameters', 'getNumberOfRequiredParameters', '__toString', 'isDisabled',
            'returnsReference', 'getClosureScopeClass', 'getClosureThis'
        ];

        $allNameGetters[] = 'hasReturnType';

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

    #[\PHPUnit\Framework\Attributes\DoesNotPerformAssertions]
    public function testCoverAllMethods(): void
    {
        $allInternalMethods = get_class_methods(\ReflectionFunction::class);
        $allMissedMethods   = [];

        foreach ($allInternalMethods as $internalMethodName) {
            if ('export' === $internalMethodName) {
                continue;
            }
            $refMethod    = new \ReflectionMethod(ReflectionFunction::class, $internalMethodName);
            $definerClass = $refMethod->getDeclaringClass()->getName();
            if (strpos($definerClass, 'Go\\ParserReflection') !== 0) {
                $allMissedMethods[] = $internalMethodName;
            }
        }

        if ($allMissedMethods) {
            $this->markTestIncomplete('Methods ' . implode(', ', $allMissedMethods) . ' are not implemented');
        }
    }

    public function testGetClosureMethod(): void
    {
        $fileNamespace = $this->parsedRefFile->getFileNamespace('Go\ParserReflection\Stub');
        $refFunc       = $fileNamespace->getFunction('noGeneratorFunc');
        $closure       = $refFunc->getClosure();

        $this->assertInstanceOf(\Closure::class, $closure);
        $retValue = $closure();
        $this->assertEquals(100, $retValue);
    }

    public function testInvokeMethod(): void
    {
        $fileNamespace = $this->parsedRefFile->getFileNamespace('Go\ParserReflection\Stub');
        $refFunc       = $fileNamespace->getFunction('funcWithReturnArgs');
        $retValue      = $refFunc->invoke(1, 2, 3);
        $this->assertEquals([1, 2, 3], $retValue);
    }

    public function testInvokeArgsMethod(): void
    {
        $fileNamespace = $this->parsedRefFile->getFileNamespace('Go\ParserReflection\Stub');
        $refFunc       = $fileNamespace->getFunction('funcWithReturnArgs');
        $retValue      = $refFunc->invokeArgs([1, 2, 3]);
        $this->assertEquals([1, 2, 3], $retValue);
    }

    public function testGetReturnTypeMethod(): void
    {
        $fileName = stream_resolve_include_path(__DIR__ . self::STUB_FILE70);

        $reflectionFile = new ReflectionFile($fileName);
        include $fileName;

        foreach ($reflectionFile->getFileNamespaces() as $fileNamespace) {
            foreach ($fileNamespace->getFunctions() as $refFunction) {
                $functionName        = $refFunction->getName();
                $originalRefFunction = new \ReflectionFunction($functionName);
                $hasReturnType       = $refFunction->hasReturnType();
                $this->assertSame(
                    $originalRefFunction->hasReturnType(),
                    $hasReturnType,
                    "Presence of return type for function {$functionName} should be equal"
                );
                if ($hasReturnType) {
                    $parsedReturnType   = $refFunction->getReturnType();
                    $originalReturnType = $originalRefFunction->getReturnType();
                    $this->assertSame($originalReturnType->allowsNull(), $parsedReturnType->allowsNull());
                    $this->assertSame($originalReturnType->isBuiltin(), $parsedReturnType->isBuiltin());
                    $this->assertSame($originalReturnType->getName(), $parsedReturnType->__toString());
                } else {
                    $this->assertSame(
                        $originalRefFunction->getReturnType(),
                        $refFunction->getReturnType()
                    );
                }
            }
        }
    }
}
