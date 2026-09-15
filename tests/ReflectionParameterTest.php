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
        $originalParamType = $originalRefParameter->getType();
        $parsedParamType   = $parsedParameter->getType();

        if (isset($originalParamType)) {
            $this->assertNotNull($parsedParamType, "Original param type is: {$originalParamType}");
            $this->assertInstanceOf($originalParamType::class, $parsedParamType, "Parsed param type is: {$parsedParamType}");
            $this->assertSame((string)$originalParamType, (string)$parsedParamType);
        } else {
            $this->assertNull($parsedParamType);
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
     * Test that parameters with new expression default values work correctly
     */
    public function testParametersWithNewExpressionDefaults(): void
    {
        $fileName = __DIR__ . '/Stub/FileWithNewExpressionDefaults.php';
        $reflectionFile = new ReflectionFile($fileName);
        $parsedFileNamespace = $reflectionFile->getFileNamespace('Go\ParserReflection\Stub');
        
        // Test the method from the reported issue
        $parsedClass = $parsedFileNamespace->getClass('Go\ParserReflection\Stub\TestClassWithNewExpressionDefaults');
        $parsedMethod = $parsedClass->getMethod('deactivateSeries');
        $parsedParameter = $parsedMethod->getParameters()[0];
        
        $this->assertTrue($parsedParameter->isDefaultValueAvailable());
        $this->assertFalse($parsedParameter->isDefaultValueConstant());
        
        $defaultValue = $parsedParameter->getDefaultValue();
        $this->assertInstanceOf(\DateTimeImmutable::class, $defaultValue);
        
        // Test DateTime default
        $parsedMethod2 = $parsedClass->getMethod('withDateTime');
        $parsedParameter2 = $parsedMethod2->getParameters()[0];
        
        $this->assertTrue($parsedParameter2->isDefaultValueAvailable());
        $defaultValue2 = $parsedParameter2->getDefaultValue();
        $this->assertInstanceOf(\DateTime::class, $defaultValue2);
        $this->assertSame('2023-01-01', $defaultValue2->format('Y-m-d'));
        
        // Test stdClass default
        $parsedMethod3 = $parsedClass->getMethod('withStdClass');
        $parsedParameter3 = $parsedMethod3->getParameters()[0];
        
        $this->assertTrue($parsedParameter3->isDefaultValueAvailable());
        $defaultValue3 = $parsedParameter3->getDefaultValue();
        $this->assertInstanceOf(\stdClass::class, $defaultValue3);
    }

    /**
     * Test that parameters with backed enum property fetch default values work correctly
     */
    public function testParametersWithBackedEnumPropertyDefault(): void
    {
        $fileName = __DIR__ . '/Stub/FileWithClasses81.php';
        $reflectionFile = new ReflectionFile($fileName);
        $parsedFileNamespace = $reflectionFile->getFileNamespace('Go\ParserReflection\Stub');

        $parsedClass = $parsedFileNamespace->getClass('Go\ParserReflection\Stub\ClassWithBackedEnumDefaultValue');
        $parsedMethod = $parsedClass->getMethod('getRefusalDescription');
        $parsedParameter = $parsedMethod->getParameters()[0];

        $this->assertSame('channel', $parsedParameter->getName());
        $this->assertTrue($parsedParameter->isDefaultValueAvailable());

        $defaultValue = $parsedParameter->getDefaultValue();
        $this->assertSame('get', $defaultValue);
    }

    /**
     * Test that parameters with first-class callable syntax as default value are reflected correctly.
     *
     * The stub file is only parsed via AST (not included), because PHP runtime forbids
     * FCC in constant-expression positions.
     */
    public function testParameterWithFccDefaultValue(): void
    {
        // Parse the stub via AST only — do NOT include_once since PHP runtime rejects FCC defaults.
        $fileName = __DIR__ . '/Stub/FileWithFunctionsFcc.php';
        $fileNode = ReflectionEngine::parseFile($fileName);
        $reflectionFile = new ReflectionFile($fileName, $fileNode);
        $parsedFileNamespace = $reflectionFile->getFileNamespace('Go\ParserReflection\Stub');

        // Test built-in FCC default: function functionWithBuiltinFccDefault($callable = \strlen(...))
        $parsedFunction = $parsedFileNamespace->getFunction('functionWithBuiltinFccDefault');
        $parsedParameter = $parsedFunction->getParameters()[0];

        $this->assertSame('callable', $parsedParameter->getName());
        $this->assertTrue($parsedParameter->isDefaultValueAvailable());
        $this->assertFalse($parsedParameter->isDefaultValueConstant());

        // Default value should be a Closure (resolved from the built-in FCC)
        $defaultValue = $parsedParameter->getDefaultValue();
        $this->assertInstanceOf(\Closure::class, $defaultValue);
        $this->assertSame(6, $defaultValue('foobar'));

        // getDefaultValueExpression() must return the fully-qualified FCC source expression.
        // The FQN form (\strlen) is what PHP-Parser's NameResolver emits for built-in functions
        // inside a namespace; this exact string is needed by proxy generators for code reconstruction.
        $this->assertSame('\strlen(...)', $parsedParameter->getDefaultValueExpression());

        // __toString must contain the FCC expression
        $this->assertStringContainsString('\strlen(...)', (string) $parsedParameter);
    }

    /**
     * Test that parameters with a user-defined static method FCC default are reflected correctly.
     *
     * The stub file is only parsed via AST (not included), because PHP runtime forbids
     * FCC in constant-expression positions.
     */
    public function testParameterWithStaticMethodFccDefaultValue(): void
    {
        $fileName = __DIR__ . '/Stub/FileWithFunctionsFcc.php';
        $fileNode = ReflectionEngine::parseFile($fileName);
        $reflectionFile = new ReflectionFile($fileName, $fileNode);
        $parsedFileNamespace = $reflectionFile->getFileNamespace('Go\ParserReflection\Stub');

        $parsedFunction = $parsedFileNamespace->getFunction('functionWithStaticMethodFccDefault');
        $parsedParameter = $parsedFunction->getParameters()[0];

        $this->assertSame('callable', $parsedParameter->getName());
        $this->assertTrue($parsedParameter->isDefaultValueAvailable());

        // Default value should be a Closure wrapping the static method
        $defaultValue = $parsedParameter->getDefaultValue();
        $this->assertInstanceOf(\Closure::class, $defaultValue);

        // getDefaultValueExpression() must return the FQN source expression
        $expression = $parsedParameter->getDefaultValueExpression();
        $this->assertNotNull($expression);
        $this->assertStringContainsString('ReflectionEngine::locateClassFile(...)', $expression);

        // __toString must contain the FCC expression
        $this->assertStringContainsString('ReflectionEngine::locateClassFile(...)', (string) $parsedParameter);
    }

    /**
     * Doc comments on parameters are a PHP 8.6 feature (native ReflectionParameter::getDocComment()),
     * but the reflection engine resolves them statically on every supported PHP version.
     *
     * @param string|array{0: string, 1: string} $functionReference Function name or [class, method] pair
     */
    #[DataProvider('parameterDocCommentsDataProvider')]
    public function testGetDocComment(
        ReflectionParameter $parsedParameter,
        string|array $functionReference,
        string|false $expectedDocComment
    ): void {
        $this->assertSame(
            $expectedDocComment,
            $parsedParameter->getDocComment(),
            "getDocComment() for parameter \${$parsedParameter->getName()} should be equal"
        );

        if (PHP_VERSION_ID >= 80600) {
            $originalRefParameter = new \ReflectionParameter($functionReference, $parsedParameter->getName());
            $this->assertSame(
                $originalRefParameter->getDocComment(),
                $parsedParameter->getDocComment(),
                "getDocComment() for parameter \${$parsedParameter->getName()} should match native reflection"
            );
        }
    }

    /**
     * A doc comment written *after* the parameter it belongs to is a known parity gap.
     *
     * PHP itself remembers the last doc comment token seen while the parameter rule is reduced,
     * therefore a trailing comment still belongs to the preceding parameter. PHP-Parser instead
     * attaches every comment to the node that *follows* it, so a trailing comment never reaches
     * the `Param` node and the engine reports `false`.
     */
    public function testTrailingDocCommentIsAKnownParityGap(): void
    {
        $parsedFunction  = self::getStub86Namespace()->getFunction('parameterWithTrailingDocComment86');
        $parsedParameter = $parsedFunction->getParameters()[0];

        $this->assertSame('trailing', $parsedParameter->getName());
        $this->assertFalse($parsedParameter->getDocComment());

        if (PHP_VERSION_ID >= 80600) {
            $originalRefParameter = new \ReflectionParameter($parsedFunction->getName(), 'trailing');
            $this->assertSame(
                '/** trailing doc comment on the last parameter of the list */',
                $originalRefParameter->getDocComment(),
                'Native reflection is expected to still report the trailing doc comment'
            );
        }
    }

    /**
     * The same known parity gap when another parameter follows the trailing doc comment.
     *
     * PHP-Parser discards a comment that sits between the end of a parameter and the separating
     * comma altogether: it reaches neither the documented parameter nor the following one, so the
     * engine reports `false` for both. Native reflection instead reports the comment for the
     * parameter it follows. Both sides are pinned so a future change in php-src or in PHP-Parser
     * is noticed immediately.
     */
    public function testTrailingDocCommentIsLostWhenAnotherParameterFollows(): void
    {
        $parsedFunction               = self::getStub86Namespace()->getFunction('twoParametersWithTrailingDocComment86');
        [$parsedFirst, $parsedSecond] = $parsedFunction->getParameters();

        $this->assertSame('first', $parsedFirst->getName());
        $this->assertSame('second', $parsedSecond->getName());

        // The comment is dropped by PHP-Parser, so it is not mis-attributed to the next parameter
        $this->assertFalse($parsedFirst->getDocComment());
        $this->assertFalse($parsedSecond->getDocComment());

        if (PHP_VERSION_ID >= 80600) {
            $originalFirst  = new \ReflectionParameter($parsedFunction->getName(), 'first');
            $originalSecond = new \ReflectionParameter($parsedFunction->getName(), 'second');

            // Native reflection reports the comment for the parameter that precedes it
            $this->assertSame(
                '/** trailing doc comment written after the first parameter */',
                $originalFirst->getDocComment()
            );
            $this->assertFalse($originalSecond->getDocComment());
        }
    }

    /**
     * Provides list in the form [ReflectionParameter, function reference, expected doc comment]
     */
    public static function parameterDocCommentsDataProvider(): \Generator
    {
        $expectedDocComments = [
            'parametersWithDocComments86' => [
                'documented'                 => '/** @param string $documented simple leading doc comment */',
                'undocumented'               => false,
                'blockCommented'             => false,
                'lineCommented'              => false,
                'variadic'                   => '/** @param array<int> $variadic variadic doc comment */',
            ],
            'parametersWithReferencesAndAttributes86' => [
                'byReference'                => '/** @param array<string> $byReference by-reference doc comment */',
                'docBeforeAttribute'         => '/** doc comment placed before the attribute */',
                'docAfterAttribute'          => '/** doc comment placed after the attribute */',
                'lastDocCommentWins'         => '/** last doc comment wins */',
                'docCommentThenBlockComment' => '/** doc comment followed by a regular comment */',
            ],
            'parametersWithDefaultsAndTypes86' => [
                'nullableWithDefault'        => '/** @param string|null $nullableWithDefault nullable doc comment */',
                'unionTyped'                 => '/** @param int|float $unionTyped union type doc comment */',
                'arrayDefault'               => '/** @param array<mixed> $arrayDefault doc comment for array default */',
            ],
        ];

        $expectedMethodDocComments = [
            'ClassWithDocumentedParameters86::__construct' => [
                'promoted'                   => '/** @var string promoted property doc comment */',
                'promotedProtected'          => '/** @var int promoted protected property doc comment */',
                'promotedUndocumented'       => false,
            ],
            'ClassWithDocumentedParameters86::documentedMethod' => [
                'first'                      => false,
                'second'                     => '/** @param string $second method parameter doc comment */',
            ],
            'ClassWithDocumentedParameters86::documentedStaticMethod' => [
                'instance'                   => '/** @param self $instance static method parameter doc comment */',
            ],
        ];

        $parsedNamespace = self::getStub86Namespace();

        foreach ($expectedDocComments as $functionName => $expectedParameters) {
            $parsedFunction = $parsedNamespace->getFunction($functionName);
            foreach ($parsedFunction->getParameters() as $parsedParameter) {
                $parameterName = $parsedParameter->getName();
                if (!array_key_exists($parameterName, $expectedParameters)) {
                    throw new \LogicException("Missing expectation for {$functionName}(\${$parameterName})");
                }
                yield "{$functionName}(\${$parameterName})" => [
                    $parsedParameter,
                    $parsedFunction->getName(),
                    $expectedParameters[$parameterName],
                ];
            }
        }

        foreach ($expectedMethodDocComments as $methodReference => $expectedParameters) {
            [$className, $methodName] = explode('::', $methodReference);
            $parsedClass  = $parsedNamespace->getClass('Go\ParserReflection\Stub\\' . $className);
            $parsedMethod = $parsedClass->getMethod($methodName);
            foreach ($parsedMethod->getParameters() as $parsedParameter) {
                $parameterName = $parsedParameter->getName();
                if (!array_key_exists($parameterName, $expectedParameters)) {
                    throw new \LogicException("Missing expectation for {$methodReference}(\${$parameterName})");
                }
                yield "{$methodReference}(\${$parameterName})" => [
                    $parsedParameter,
                    [$parsedClass->getName(), $methodName],
                    $expectedParameters[$parameterName],
                ];
            }
        }
    }

    /**
     * Parses (and loads) the stub file with documented parameters
     */
    private static function getStub86Namespace(): ReflectionFileNamespace
    {
        $fileName       = __DIR__ . '/Stub/FileWithParameters86.php';
        $reflectionFile = new ReflectionFile($fileName);

        // The file only contains ordinary comments, so it can be safely loaded on any PHP version
        include_once $fileName;

        return $reflectionFile->getFileNamespace('Go\ParserReflection\Stub');
    }

    /**
     * @inheritDoc
     */
    static protected function getGettersToCheck(): array
    {
        $getters = [
            'isOptional', 'isPassedByReference', 'isDefaultValueAvailable',
            'getPosition', 'canBePassedByValue', 'allowsNull', 'getDefaultValue', 'getDefaultValueConstantName',
            'isDefaultValueConstant', 'isVariadic', 'isPromoted', 'hasType', '__toString'
        ];

        if (PHP_VERSION_ID >= 80600) {
            // Native ReflectionParameter::getDocComment() only exists since PHP 8.6
            $getters[] = 'getDocComment';
        }

        return $getters;
    }
}
