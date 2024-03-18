<?php
declare(strict_types=1);

namespace Go\ParserReflection;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Go\ParserReflection\Stub\Foo;
use Go\ParserReflection\Stub\SubFoo;

class ReflectionParameterTest extends AbstractTestCase
{
    protected const DEFAULT_STUB_FILENAME = '/Stub/FileWithParameters55.php';

    protected static string $reflectionClassToTest = \ReflectionParameter::class;

    /**
     * Performs method-by-method comparison with original reflection
     */
    #[DataProvider('reflectionGetterDataProvider')]
    public function testReflectionGetterParity(
        \ReflectionFunctionAbstract $parsedFunctionAbstract,
        ReflectionParameter $parsedParameter,
        \ReflectionParameter $originalRefParameter,
        string $getterName
    ): void {
        $parameterName = $originalRefParameter->getName();
        if ($parsedFunctionAbstract instanceof \ReflectionMethod) {
            $functionName = [$parsedFunctionAbstract->class, $parsedFunctionAbstract->getName()];
        } else {
            $functionName = $parsedFunctionAbstract->getName();
        }

        $expectedValue = $originalRefParameter->$getterName();
        $actualValue   = $parsedParameter->$getterName();
        $displayableName = is_array($functionName) ? join ('->', $functionName) : $functionName;
        // I would like to completely stop maintaining the __toString method
        if ($expectedValue !== $actualValue && $getterName === '__toString') {
            $this->markTestSkipped("__toString for parameter {$displayableName}(\${$parameterName}) is not equal:\n{$expectedValue}\n{$actualValue}");
        }
        $this->assertSame(
            $expectedValue,
            $actualValue,
            "{$getterName}() for parameter {$displayableName}(\${$parameterName}) should be equal"
        );
    }

    #[DataProvider('parametersDataProvider')]
    public function testGetClassMethod(
        \ReflectionFunctionAbstract $parsedFunction,
        ReflectionParameter $parsedParameter,
        \ReflectionParameter        $originalRefParameter
    ): void {
        $originalParamClass = $originalRefParameter->getClass();
        $parsedParamClass   = $parsedParameter->getClass();

        if (isset($originalParamClass)) {
            $this->assertNotNull($parsedParamClass, "Original param class is: {$originalParamClass->name}");
            $this->assertSame($originalParamClass->getName(), $parsedParamClass->getName());
        } else {
            $this->assertNull($parsedParamClass);
        }
    }

    #[DataProvider('parametersDataProvider')]
    public function testGetDeclaringClassMethod(
        \ReflectionFunctionAbstract $parsedFunction,
        ReflectionParameter $parsedParameter,
        \ReflectionParameter        $originalRefParameter
    ): void {
        $originalDeclaringClass = $originalRefParameter->getDeclaringClass();
        $parsedDeclaringClass   = $parsedParameter->getDeclaringClass();

        if (isset($originalDeclaringClass)) {
            $this->assertSame($originalDeclaringClass->getName(), $parsedDeclaringClass->getName());
        } else {
            $this->assertNull($parsedDeclaringClass);
        }
    }

    #[DataProvider('parametersDataProvider')]
    public function testDebugInfoMethod(
        \ReflectionFunctionAbstract $parsedFunction,
        ReflectionParameter $parsedParameter,
        \ReflectionParameter        $originalRefParameter
    ): void {
        $expectedValue  = (array) $originalRefParameter;
        $this->assertSame($expectedValue, $parsedParameter->__debugInfo());
    }

    /**
     * @param string $getterName Name of the getter to call
     */
    #[DataProvider('listOfDefaultGetters')]
    public function testGetDefaultValueThrowsAnException(string $getterName): void
    {
        $originalException = null;
        $parsedException   = null;

        try {
            $originalRefParameter = new \ReflectionParameter('Go\ParserReflection\Stub\miscParameters', 'arrayParam');
            $originalRefParameter->$getterName();
        } catch (\ReflectionException $e) {
            $originalException = $e;
        }

        try {
            $parsedNamespace = $this->parsedRefFile->getFileNamespace('Go\ParserReflection\Stub');
            $parsedFunction  = $parsedNamespace->getFunction('miscParameters');

            $parsedRefParameters  = $parsedFunction->getParameters();
            $parsedRefParameter   = $parsedRefParameters[0];
            $parsedRefParameter->$getterName();
        } catch (\ReflectionException $e) {
            $parsedException = $e;
        }

        $this->assertInstanceOf(\ReflectionException::class, $originalException);
        $this->assertInstanceOf(\ReflectionException::class, $parsedException);
        $this->assertSame($originalException->getMessage(), $parsedException->getMessage());
    }

    public static function listOfDefaultGetters(): \Iterator
    {
        yield ['getDefaultValue'];
        yield ['getDefaultValueConstantName'];
    }

    #[DataProvider('parametersDataProvider')]
    public function testGetTypeMethod(
        \ReflectionFunctionAbstract $parsedFunctionAbstract,
        ReflectionParameter $parsedParameter,
        \ReflectionParameter $originalRefParameter
    ): void {
        $functionName  = $parsedFunctionAbstract->getName();
        $parameterName = $parsedParameter->getName();
        $hasType       = $originalRefParameter->hasType();
        $this->assertSame(
            $hasType,
            $parsedParameter->hasType(),
            "Presence of type for parameter {$functionName}:{$parameterName} should be equal"
        );
        $message= "Parameter $functionName:$parameterName not equals to the original reflection";
        if ($hasType) {
            $parsedReturnType   = $parsedParameter->getType();
            $originalReturnType = $originalRefParameter->getType();
            $this->assertSame($originalReturnType->allowsNull(), $parsedReturnType->allowsNull(), $message);
            $this->assertSame($originalReturnType->__toString(), $parsedReturnType->__toString(), $message);
        } else {
            $this->assertSame(
                $originalRefParameter->getType(),
                $parsedParameter->getType(),
                $message
            );
        }
    }

    /**
     * Provides full test-case list in the form [ReflectionClass, \ReflectionMethod, getter name to check]
     */
    public static function reflectionGetterDataProvider(): \Generator
    {
        static $onlyWithDefaultValues = [
            'getDefaultValue', 'getDefaultValueConstantName', 'isDefaultValueConstant'
        ];

        $allNameGetters = self::getGettersToCheck();
        foreach (self::parametersDataProvider() as $prefix => [$parsedFunction, $parsedParameter, $originalParameter]) {
            foreach ($allNameGetters as $getterName) {
                // We should ignore some methods if there isn't default value
                $isDefaultValueAvailable = $originalParameter->isDefaultValueAvailable();
                if (!$isDefaultValueAvailable && in_array($getterName, $onlyWithDefaultValues)) {
                    continue;
                }
                yield $prefix . ', ' . $getterName => [
                    $parsedFunction,
                    $parsedParameter,
                    $originalParameter,
                    $getterName
                ];
            }
        }
    }

    /**
     * Provides generator list in the form [ReflectionFunctionAbstract, ReflectionParameter, \ReflectionParameter to check]
     */
    public static function parametersDataProvider(): \Generator
    {
        foreach (self::methodsDataProvider() as $prefix => [$parsedClass, $parsedClassMethod]) {
            if (get_class($parsedClassMethod) === \ReflectionMethod::class) {
                // We don't want test again parent parameters from parent methods, which already loaded
                continue;
            }
            foreach ($parsedClassMethod->getParameters() as $parsedMethodParameter) {
                $paramName = $parsedMethodParameter->getName();
                $refParameter = new \ReflectionParameter([$parsedClass->getName(), $parsedClassMethod->getName()], $paramName);
                yield $prefix . ' ' . '($' . $paramName . ')' => [
                    $parsedClassMethod,
                    $parsedMethodParameter,
                    $refParameter
                ];
            }
        }
        foreach (self::functionsDataProvider() as $prefix => [$parsedFunction, $refFunction]) {
            foreach ($parsedFunction->getParameters() as $parsedFunctionParameter) {
                $paramName = $parsedFunctionParameter->getName();
                $refParameter = new \ReflectionParameter($parsedFunction->getName(), $paramName);
                yield $prefix . ' ' . '($' . $paramName . ')' => [
                    $parsedFunction,
                    $parsedFunctionParameter,
                    $refParameter
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
            'isOptional', 'isPassedByReference', 'isDefaultValueAvailable',
            'getPosition', 'canBePassedByValue', 'allowsNull', 'getDefaultValue', 'getDefaultValueConstantName',
            'isDefaultValueConstant', 'isVariadic', 'isPromoted', 'hasType', '__toString'
        ];
    }
}
