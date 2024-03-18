<?php
declare(strict_types=1);

namespace Go\ParserReflection;

use PHPUnit\Framework\Attributes\DataProvider;

class ReflectionMethodTest extends AbstractTestCase
{
    protected static string $reflectionClassToTest = \ReflectionMethod::class;

    /**
     * Performs method-by-method comparison with original reflection
     */
    #[DataProvider('reflectionGetterDataProvider')]
    public function testReflectionGetterParity(
        ReflectionClass $parsedClass,
        \ReflectionMethod|ReflectionMethod $parsedMethod,
        \ReflectionMethod $refMethod,
        string $getterName
    ): void {
        $methodName   = $refMethod->getName();
        $className    = $parsedClass->getName();

        $expectedValue = $refMethod->$getterName();
        $actualValue   = $parsedMethod->$getterName();
        // I would like to completely stop maintaining the __toString method
        if ($expectedValue !== $actualValue && $getterName === '__toString') {
            $this->markTestSkipped("__toString for method {$className}::{$methodName} is not equal:\n{$expectedValue}{$actualValue}");
        }
        $this->assertSame(
            $expectedValue,
            $actualValue,
            "$getterName() for method $className->$methodName() should be equal"
        );
    }

    public function testGetClosureMethod(): void
    {
        $refMethod = $this->parsedRefClass->getMethod('funcWithDocAndBody');
        $closure   = $refMethod->getClosure(null);

        $this->assertInstanceOf(\Closure::class, $closure);
        $retValue = $closure();
        $this->assertSame('hello', $retValue);
    }

    public function testInvokeMethod(): void
    {
        $refMethod = $this->parsedRefClass->getMethod('funcWithReturnArgs');
        $retValue  = $refMethod->invoke(null, 1, 2, 3);
        $this->assertSame([1, 2, 3], $retValue);
    }

    public function testInvokeArgsMethod(): void
    {
        $refMethod = $this->parsedRefClass->getMethod('funcWithReturnArgs');
        $retValue  = $refMethod->invokeArgs(null, [1, 2, 3]);
        $this->assertSame([1, 2, 3], $retValue);
    }

    public function testCallProtectedMethod(): void
    {
        $refMethod = $this->parsedRefClass->getMethod('protectedStaticFunc');
        $retValue = $refMethod->invokeArgs(null, []);
        $this->assertEquals(null, $retValue);
    }

    #[DataProvider('methodsDataProvider')]
    public function testDebugInfoMethod(
        ReflectionClass $parsedRefClass,
        \ReflectionMethod|ReflectionMethod $parsedRefMethod,
        \ReflectionMethod $originalRefMethod
    ): void {
        $methodName = $originalRefMethod->getName();
        $className  = $parsedRefClass->getName();
        if (!$parsedRefMethod instanceof ReflectionMethod) {
            $this->markTestSkipped("Native reflection method {$className}->{$methodName}() represented similarly");
        }
        $expectedValue = (array) $originalRefMethod;
        $this->assertSame($expectedValue, $parsedRefMethod->__debugInfo());
    }

    #[DataProvider('methodsDataProvider')]
    public function testReturnTypeMethods(
        ReflectionClass $parsedRefClass,
        \ReflectionMethod|ReflectionMethod $parsedRefMethod,
        \ReflectionMethod $originalRefMethod
    ): void {
        $methodName      = $originalRefMethod->getName();
        $className       = $parsedRefClass->getName();

        $hasType = $parsedRefMethod->hasReturnType();
        $this->assertSame(
            $originalRefMethod->hasReturnType(),
            $hasType,
            "Presence of return type for method {$className}:{$methodName} should be equal"
        );
        $message= "Type information for {$className}::$methodName() not equals to the original reflection";
        if ($hasType) {
            $parsedType   = $parsedRefMethod->getReturnType();
            $originalType = $originalRefMethod->getReturnType();
            $this->assertSame($originalType->allowsNull(), $parsedType->allowsNull(), $message);
            $this->assertSame($originalType->__toString(), $parsedType->__toString(), $message);
        } else {
            $this->assertSame(
                $originalRefMethod->getReturnType(),
                $parsedRefMethod->getReturnType(),
                $message
            );
        }
    }

    #[DataProvider('methodsDataProvider')]
    public function testPrototypeMethods(
        ReflectionClass $parsedRefClass,
        \ReflectionMethod|ReflectionMethod $parsedRefMethod,
        \ReflectionMethod $originalRefMethod
    ): void {
        $methodName      = $originalRefMethod->getName();
        $className       = $parsedRefClass->getName();

        $hasPrototype = $parsedRefMethod->hasPrototype();
        $this->assertSame(
            $originalRefMethod->hasPrototype(),
            $hasPrototype,
            "Presence of prototype for method {$className}:{$methodName} should be equal"
        );
        $message= "Prototype information for {$className}::$methodName() not equals to the original reflection";
        if ($hasPrototype) {
            $parsedPrototype   = $parsedRefMethod->getPrototype();
            $originalPrototype = $originalRefMethod->getPrototype();
            $this->assertSame($originalPrototype->getDeclaringClass()->getName(), $parsedPrototype->getDeclaringClass()->getName(), $message);
        }
    }

    /**
     * Provides full test-case list in the form [ReflectionClass, ReflectionMethod, \ReflectionMethod, getter name to check]
     */
    public static function reflectionGetterDataProvider(): \Generator
    {
        $allNameGetters = self::getGettersToCheck();
        foreach (self::methodsDataProvider() as $prefix => [$parsedClass, $parsedMethod, $classMethod]) {
            foreach ($allNameGetters as $getterName) {
                yield $prefix . ', ' . $getterName => [
                    $parsedClass,
                    $parsedMethod,
                    $classMethod,
                    $getterName
                ];
            }
        }
    }

    /**
     * Returns list of ReflectionMethod getters that be checked directly without additional arguments
     */
    protected static function getGettersToCheck(): array
    {
        return [
            'getStartLine', 'getEndLine', 'getDocComment', 'getExtension', 'getExtensionName', 'getName',
            'getNamespaceName', 'getShortName', 'inNamespace', 'getStaticVariables', 'isClosure', 'isDeprecated',
            'isInternal', 'isUserDefined', 'isAbstract', 'isConstructor', 'isDestructor', 'isFinal', 'isPrivate',
            'isProtected', 'isPublic', 'isStatic', 'isVariadic', 'isGenerator', 'getNumberOfParameters',
            'getNumberOfRequiredParameters', 'returnsReference', 'getClosureScopeClass', 'getClosureThis',
            'hasReturnType', '__toString'
        ];
    }
}
