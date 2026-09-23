<?php
declare(strict_types=1);
/**
 * Parser Reflection API
 *
 * @copyright Copyright 2025, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\ParserReflection;

use Go\ParserReflection\Locator\CallableLocator;
use Go\ParserReflection\Locator\ComposerLocator;
use PHPUnit\Framework\TestCase;

/**
 * Verifies the function-like reflection methods that used to fall back to the uninitialized
 * internal reflection, together with the PHP 8.4 `#[\Deprecated]` attribute support.
 *
 * Test methods that must not load the stub file are declared before the ones comparing the
 * results with the native reflection, because the latter have to include the stub file.
 */
class DeprecatedAndFunctionLikeGapsTest extends TestCase
{
    public const STUB_FILE = '/Stub/FileWithDeprecatedFeatures84.php';

    public const STUB_NAMESPACE = 'Go\ParserReflection\Stub';

    public const STUB_CLASS = 'Go\ParserReflection\Stub\ClassWithPhp84DeprecatedFeatures';

    /**
     * Names of the deprecated methods of the stub class
     */
    private const DEPRECATED_METHODS = ['deprecatedMethod', 'deprecatedStaticMethod', 'importedDeprecatedMethod'];

    /**
     * Names of the deprecated constants of the stub class
     */
    private const DEPRECATED_CONSTANTS = [
        'DEPRECATED_CONSTANT',
        'DEPRECATED_CONSTANT_WITH_ARGUMENTS',
        'IMPORTED_DEPRECATED_CONSTANT',
    ];

    /**
     * Names of the deprecated functions of the stub file, without the namespace prefix
     */
    private const DEPRECATED_FUNCTIONS = [
        'php84DeprecatedFunction',
        'php84DeprecatedFunctionWithArguments',
        'php84ImportedDeprecatedFunction',
    ];

    /**
     * Internal function that receives the #[\Deprecated] attribute in PHP 8.6 only
     *
     * @see https://github.com/goaop/parser-reflection/issues/215
     */
    private const PHP86_DEPRECATED_INTERNAL_FUNCTION = 'strcoll';

    /**
     * Internal function that already carries the #[\Deprecated] attribute long before PHP 8.6
     */
    private const LEGACY_DEPRECATED_INTERNAL_FUNCTION = 'utf8_encode';

    /**
     * Internal function that is not deprecated at all, used as a negative control
     */
    private const PLAIN_INTERNAL_FUNCTION = 'strlen';

    private string $stubFileName;

    protected function setUp(): void
    {
        $resolvedFileName = stream_resolve_include_path(__DIR__ . self::STUB_FILE);
        $this->assertIsString($resolvedFileName, 'Stub file with deprecated features should be available');

        $this->stubFileName = $resolvedFileName;
    }

    protected function tearDown(): void
    {
        // Restores the default locator for the following tests, because some of them replace it
        ReflectionEngine::init(new ComposerLocator());
    }

    public function testMethodDeprecationIsResolvedWithoutLoadingTheClass(): void
    {
        $parsedClass = $this->getNotLoadedStubClass();

        foreach (self::DEPRECATED_METHODS as $methodName) {
            $this->assertTrue(
                $parsedClass->getMethod($methodName)->isDeprecated(),
                "Method {$methodName}() should be reported as deprecated"
            );
        }
        $this->assertFalse($parsedClass->getMethod('actualMethod')->isDeprecated());
        $this->assertStubClassIsNotLoaded();
    }

    public function testConstantDeprecationIsResolvedWithoutLoadingTheClass(): void
    {
        $parsedClass = $this->getNotLoadedStubClass();

        foreach (self::DEPRECATED_CONSTANTS as $constantName) {
            $this->assertTrue(
                $parsedClass->getReflectionConstant($constantName)->isDeprecated(),
                "Constant {$constantName} should be reported as deprecated"
            );
        }
        $this->assertFalse($parsedClass->getReflectionConstant('ACTUAL_CONSTANT')->isDeprecated());
        $this->assertStubClassIsNotLoaded();
    }

    public function testFunctionLikeGapsAreResolvedWithoutLoadingTheClass(): void
    {
        $parsedClass  = $this->getNotLoadedStubClass();
        $parsedMethod = $parsedClass->getMethod('actualMethod');

        $this->assertFalse($parsedMethod->hasTentativeReturnType());
        $this->assertNull($parsedMethod->getTentativeReturnType());
        $this->assertSame([], $parsedMethod->getClosureUsedVariables());
        $this->assertNull($parsedMethod->getClosureCalledClass());
        $this->assertStubClassIsNotLoaded();
    }

    public function testCreateFromMethodNameDoesNotLoadTheClass(): void
    {
        ReflectionEngine::init($this->createStubLocator());

        $parsedMethod = ReflectionMethod::createFromMethodName(self::STUB_CLASS . '::deprecatedStaticMethod');

        $this->assertInstanceOf(ReflectionMethod::class, $parsedMethod);
        $this->assertSame('deprecatedStaticMethod', $parsedMethod->getName());
        $this->assertSame(self::STUB_CLASS, $parsedMethod->getDeclaringClass()->getName());
        $this->assertTrue($parsedMethod->isStatic());
        $this->assertTrue($parsedMethod->isDeprecated());
        $this->assertStubClassIsNotLoaded();
    }

    public function testCreateFromMethodNameRejectsInvalidMethodName(): void
    {
        $this->expectException(\ReflectionException::class);

        ReflectionMethod::createFromMethodName('thisIsNotAMethodName');
    }

    public function testFunctionDeprecationIsResolvedWithoutLoadingTheFile(): void
    {
        $parsedNamespace = $this->getStubFileNamespace();

        foreach (self::DEPRECATED_FUNCTIONS as $functionName) {
            $this->assertFalse(
                function_exists(self::STUB_NAMESPACE . '\\' . $functionName),
                "Function {$functionName}() should not be declared yet"
            );
            $this->assertTrue(
                $parsedNamespace->getFunction($functionName)->isDeprecated(),
                "Function {$functionName}() should be reported as deprecated"
            );
        }
        $this->assertFalse($parsedNamespace->getFunction('php84PlainFunction')->isDeprecated());
    }

    public function testMethodParityWithNativeReflection(): void
    {
        $parsedClass = $this->includeStubFileAndGetParsedClass();
        $nativeClass = new \ReflectionClass(self::STUB_CLASS);

        foreach ($nativeClass->getMethods() as $nativeMethod) {
            $methodName   = $nativeMethod->getName();
            $parsedMethod = $parsedClass->getMethod($methodName);
            $message      = "Method {$methodName}() should be reflected as the native one";

            $this->assertSame($nativeMethod->isDeprecated(), $parsedMethod->isDeprecated(), $message);
            $this->assertSame($nativeMethod->hasTentativeReturnType(), $parsedMethod->hasTentativeReturnType(), $message);
            $this->assertSame($nativeMethod->getTentativeReturnType(), $parsedMethod->getTentativeReturnType(), $message);
            $this->assertSame($nativeMethod->getClosureUsedVariables(), $parsedMethod->getClosureUsedVariables(), $message);
            $this->assertSame($nativeMethod->getClosureCalledClass(), $parsedMethod->getClosureCalledClass(), $message);
        }
    }

    public function testConstantParityWithNativeReflection(): void
    {
        $parsedClass = $this->includeStubFileAndGetParsedClass();
        $nativeClass = new \ReflectionClass(self::STUB_CLASS);

        foreach ($nativeClass->getReflectionConstants() as $nativeConstant) {
            $constantName = $nativeConstant->getName();
            $this->assertSame(
                $nativeConstant->isDeprecated(),
                $parsedClass->getReflectionConstant($constantName)->isDeprecated(),
                "Constant {$constantName} should be reflected as the native one"
            );
        }
    }

    public function testFunctionParityWithNativeReflection(): void
    {
        $parsedNamespace = $this->includeStubFileAndGetParsedNamespace();

        $functionNames = array_merge(self::DEPRECATED_FUNCTIONS, ['php84PlainFunction']);
        foreach ($functionNames as $functionName) {
            $parsedFunction = $parsedNamespace->getFunction($functionName);
            $nativeFunction = new \ReflectionFunction(self::STUB_NAMESPACE . '\\' . $functionName);
            $message        = "Function {$functionName}() should be reflected as the native one";

            $this->assertSame($nativeFunction->isDeprecated(), $parsedFunction->isDeprecated(), $message);
            $this->assertSame($nativeFunction->isAnonymous(), $parsedFunction->isAnonymous(), $message);
            $this->assertSame($nativeFunction->isStatic(), $parsedFunction->isStatic(), $message);
            $this->assertSame($nativeFunction->hasTentativeReturnType(), $parsedFunction->hasTentativeReturnType(), $message);
            $this->assertSame($nativeFunction->getTentativeReturnType(), $parsedFunction->getTentativeReturnType(), $message);
            $this->assertSame($nativeFunction->getClosureUsedVariables(), $parsedFunction->getClosureUsedVariables(), $message);
            $this->assertSame($nativeFunction->getClosureCalledClass(), $parsedFunction->getClosureCalledClass(), $message);
        }
    }

    public function testCreateFromMethodNameParityWithNativeReflection(): void
    {
        $this->includeStubFile();

        $methodReference = self::STUB_CLASS . '::deprecatedStaticMethod';
        $parsedMethod    = ReflectionMethod::createFromMethodName($methodReference);
        $nativeMethod    = \ReflectionMethod::createFromMethodName($methodReference);

        $this->assertInstanceOf(ReflectionMethod::class, $parsedMethod);
        $this->assertSame($nativeMethod->getName(), $parsedMethod->getName());
        $this->assertSame($nativeMethod->getDeclaringClass()->getName(), $parsedMethod->getDeclaringClass()->getName());
        $this->assertSame($nativeMethod->isStatic(), $parsedMethod->isStatic());
        $this->assertSame($nativeMethod->isPublic(), $parsedMethod->isPublic());
        $this->assertSame($nativeMethod->isDeprecated(), $parsedMethod->isDeprecated());
    }

    /**
     * Internal functions have no source file, so the AST parser can never reflect them and the
     * native reflection is always used for them instead.
     */
    public function testInternalFunctionsAreReflectedByTheNativeFallback(): void
    {
        $parsedNamespace   = $this->getStubFileNamespace();
        $internalFunctions = [
            self::PHP86_DEPRECATED_INTERNAL_FUNCTION,
            self::LEGACY_DEPRECATED_INTERNAL_FUNCTION,
            self::PLAIN_INTERNAL_FUNCTION,
        ];

        foreach ($internalFunctions as $functionName) {
            $this->assertTrue(function_exists($functionName), "Function {$functionName}() should be available");

            $nativeFunction = new \ReflectionFunction($functionName);
            $this->assertTrue($nativeFunction->isInternal(), "Function {$functionName}() should be internal");
            $this->assertFalse(
                $nativeFunction->getFileName(),
                "Function {$functionName}() has no source file, therefore it can not be parsed"
            );
            $this->assertFalse(
                $parsedNamespace->hasFunction($functionName),
                "Function {$functionName}() should never be resolved to a parsed reflection"
            );
        }

        // Parsed functions are always user-defined, so internal ones can only be served by the native
        // reflection, and the parsed one is a drop-in replacement for the native \ReflectionFunction
        $parsedFunction = $parsedNamespace->getFunction('php84PlainFunction');
        $this->assertInstanceOf(\ReflectionFunction::class, $parsedFunction);
        $this->assertFalse($parsedFunction->isInternal());
        $this->assertTrue($parsedFunction->isUserDefined());
    }

    /**
     * The #[\Deprecated] status of internal functions is reflected on every supported runtime,
     * the mechanism is not specific to the functions deprecated in PHP 8.6.
     */
    public function testDeprecatedInternalFunctionIsReflectedOnEveryRuntime(): void
    {
        $functionName   = self::LEGACY_DEPRECATED_INTERNAL_FUNCTION;
        $nativeFunction = new \ReflectionFunction($functionName);

        $this->assertTrue($nativeFunction->isDeprecated(), "Function {$functionName}() is deprecated since PHP 8.2");
        $this->assertSame('8.2', $this->getDeprecatedAttributeArguments($nativeFunction)['since']);
        $this->assertStringContainsString('deprecated', $nativeFunction->__toString());

        $plainFunction = new \ReflectionFunction(self::PLAIN_INTERNAL_FUNCTION);
        $this->assertFalse($plainFunction->isDeprecated());
        $this->assertSame([], $plainFunction->getAttributes(\Deprecated::class));
        $this->assertStringNotContainsString('deprecated', $plainFunction->__toString());
    }

    public function testInternalFunctionDeprecatedInPhp86IsReflectedWithItsAttributePayload(): void
    {
        if (PHP_VERSION_ID < 80600) {
            $this->markTestSkipped('Internal function strcoll() is deprecated since PHP 8.6 only');
        }

        $nativeFunction = new \ReflectionFunction(self::PHP86_DEPRECATED_INTERNAL_FUNCTION);

        $this->assertTrue($nativeFunction->isDeprecated());
        $arguments = $this->getDeprecatedAttributeArguments($nativeFunction);
        $this->assertSame('8.6', $arguments['since']);
        $this->assertSame('use Collator::compare() instead', $arguments['message']);
        $this->assertStringContainsString('deprecated', $nativeFunction->__toString());

        $deprecated = $nativeFunction->getAttributes(\Deprecated::class)[0]->newInstance();
        $this->assertInstanceOf(\Deprecated::class, $deprecated);
        $this->assertSame('8.6', $deprecated->since);
    }

    public function testInternalFunctionDeprecatedInPhp86IsNotDeprecatedBefore(): void
    {
        if (PHP_VERSION_ID >= 80600) {
            $this->markTestSkipped('Internal function strcoll() is deprecated since PHP 8.6');
        }

        $nativeFunction = new \ReflectionFunction(self::PHP86_DEPRECATED_INTERNAL_FUNCTION);

        $this->assertFalse($nativeFunction->isDeprecated());
        $this->assertSame([], $nativeFunction->getAttributes(\Deprecated::class));
        $this->assertStringNotContainsString('deprecated', $nativeFunction->__toString());
    }

    /**
     * Returns the arguments of the single #[\Deprecated] attribute of the given function
     *
     * @return array<string, string>
     */
    private function getDeprecatedAttributeArguments(\ReflectionFunction $function): array
    {
        $functionName = $function->getName();
        $attributes   = $function->getAttributes(\Deprecated::class);
        $this->assertCount(1, $attributes, "Function {$functionName}() should have one #[\\Deprecated] attribute");
        $this->assertSame(\Deprecated::class, $attributes[0]->getName());

        /** @var array<string, string> $arguments */
        $arguments = $attributes[0]->getArguments();
        $this->assertArrayHasKey('since', $arguments, "Function {$functionName}() should report the deprecation version");

        return $arguments;
    }

    /**
     * Returns a locator that resolves the stub class without asking composer for it
     */
    private function createStubLocator(): CallableLocator
    {
        $stubFileName = $this->stubFileName;

        return new CallableLocator(
            static fn(string $className): false|string
                => $className === self::STUB_CLASS ? $stubFileName : false
        );
    }

    /**
     * Returns the parsed stub class, resolved by name only, without loading it into memory
     */
    private function getNotLoadedStubClass(): ReflectionClass
    {
        ReflectionEngine::init($this->createStubLocator());

        return new ReflectionClass(self::STUB_CLASS);
    }

    private function getStubFileNamespace(): ReflectionFileNamespace
    {
        $reflectionFile = new ReflectionFile($this->stubFileName);

        return $reflectionFile->getFileNamespace(self::STUB_NAMESPACE);
    }

    private function includeStubFile(): void
    {
        include_once $this->stubFileName;
    }

    private function includeStubFileAndGetParsedClass(): ReflectionClass
    {
        $this->includeStubFile();

        return $this->getStubFileNamespace()->getClass(self::STUB_CLASS);
    }

    private function includeStubFileAndGetParsedNamespace(): ReflectionFileNamespace
    {
        $this->includeStubFile();

        return $this->getStubFileNamespace();
    }

    private function assertStubClassIsNotLoaded(): void
    {
        $this->assertFalse(class_exists(self::STUB_CLASS, false), 'Stub class should not be loaded');
    }
}
