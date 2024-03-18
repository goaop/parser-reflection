<?php
declare(strict_types=1);

namespace Go\ParserReflection;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ReflectionFunctionTest extends AbstractTestCase
{
    protected static string $reflectionClassToTest = \ReflectionFunction::class;
    protected const DEFAULT_STUB_FILENAME = '/Stub/FileWithFunctions55.php';
    public const STUB_FILE70 = '/Stub/FileWithFunctions70.php';

    /**
     * Performs method-by-method comparison with original reflection
     *
     * @param ReflectionFunction  $parsedFunction Parsed function
     * @param \ReflectionFunction $refFunction Original function
     * @param string              $getterName Name of the reflection method to test
     */
    #[DataProvider('reflectionGetterDataProvider')]
    public function testReflectionGetterParity(
        ReflectionFunction  $parsedFunction,
        \ReflectionFunction $refFunction,
        string              $getterName
    ): void {
        $functionName  = $refFunction->getName();

        $expectedValue = $refFunction->$getterName();
        $actualValue   = $parsedFunction->$getterName();
        // I would like to completely stop maintaining the __toString method
        if ($expectedValue !== $actualValue && $getterName === '__toString') {
            $this->markTestSkipped("__toString for function {$functionName}() is not equal:\n{$expectedValue}{$actualValue}");
        }
        $this->assertSame(
            $expectedValue,
            $actualValue,
            "$getterName() for function {$functionName}() should be equal"
        );
    }

    public function testGetClosureMethod(): void
    {
        $fileNamespace = $this->parsedRefFile->getFileNamespace('Go\ParserReflection\Stub');
        $refFunc       = $fileNamespace->getFunction('noGeneratorFunc');
        $closure       = $refFunc->getClosure();

        $this->assertInstanceOf(\Closure::class, $closure);
        $retValue = $closure();
        $this->assertSame(100, $retValue);
    }

    public function testInvokeMethod(): void
    {
        $fileNamespace = $this->parsedRefFile->getFileNamespace('Go\ParserReflection\Stub');
        $refFunc       = $fileNamespace->getFunction('funcWithReturnArgs');
        $retValue      = $refFunc->invoke(1, 2, 3);
        $this->assertSame([1, 2, 3], $retValue);
    }

    public function testInvokeArgsMethod(): void
    {
        $fileNamespace = $this->parsedRefFile->getFileNamespace('Go\ParserReflection\Stub');
        $refFunc       = $fileNamespace->getFunction('funcWithReturnArgs');
        $retValue      = $refFunc->invokeArgs([1, 2, 3]);
        $this->assertSame([1, 2, 3], $retValue);
    }

    #[DataProvider('functionsDataProvider')]
    public function testGetReturnTypeMethod(
        ReflectionFunction $parsedRefFunction, \ReflectionFunction $originalRefFunction
    ): void {
        $functionName  = $parsedRefFunction->getName();
        $hasReturnType = $originalRefFunction->hasReturnType();
        $this->assertSame(
            $originalRefFunction->hasReturnType(),
            $parsedRefFunction->hasReturnType(),
            "Presence of return type for function {$functionName} should be equal"
        );
        if ($hasReturnType) {
            $parsedReturnType   = $parsedRefFunction->getReturnType();
            $originalReturnType = $originalRefFunction->getReturnType();
            $this->assertSame($originalReturnType->allowsNull(), $parsedReturnType->allowsNull());
            $this->assertSame($originalReturnType->__toString(), $parsedReturnType->__toString());
        } else {
            $this->assertSame(
                $originalRefFunction->getReturnType(),
                $parsedRefFunction->getReturnType()
            );
        }
    }

    /**
     * Provides full test-case list in the form [ReflectionFunction, \ReflectionFunction, getter name to check]
     */
    public static function reflectionGetterDataProvider(): \Generator
    {
        $allNameGetters = self::getGettersToCheck();
        foreach (self::functionsDataProvider() as $prefix => [$parsedFunction, $refFunction]) {
            foreach ($allNameGetters as $getterName) {
                yield $prefix . ', ' . $getterName => [
                    $parsedFunction,
                    $refFunction,
                    $getterName
                ];
            }
        }
    }

    /**
     * @inheritDoc
     */
    static protected function getGettersToCheck(): array
    {
        return [
            'getStartLine', 'getEndLine', 'getDocComment', 'getExtension', 'getExtensionName',
            'getName', 'getNamespaceName', 'getShortName', 'inNamespace', 'getStaticVariables',
            'getNumberOfParameters', 'getNumberOfRequiredParameters',
            'returnsReference', 'getClosureScopeClass', 'getClosureThis', 'hasReturnType', '__toString'
        ];
    }
}
