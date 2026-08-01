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
use Go\ParserReflection\Resolver\NodeExpressionResolver;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;

/**
 * Verifies resolving of closures, arrow functions and first-class callables used in constant expressions.
 *
 * Stub file with such constant expressions uses PHP 8.5 syntax, therefore it is only parsed, never included.
 */
class ConstantExpressionClosuresTest extends TestCase
{
    public const CLOSURES_STUB_FILE = '/Stub/FileWithConstExprClosures85.php';

    public const INITIALIZERS_STUB_FILE = '/Stub/FileWithNewInInitializers81.php';

    public const STUB_NAMESPACE = 'Go\ParserReflection\Stub';

    public const CLOSURES_STUB_CLASS = 'Go\ParserReflection\Stub\ClassWithConstExprClosures';

    public const ATTRIBUTE_STUB_CLASS = 'Go\ParserReflection\Stub\ConstExprClosureAttribute';

    public const INITIALIZERS_STUB_CLASS = 'Go\ParserReflection\Stub\ClassWithNewInInitializers';

    public const INITIALIZERS_DEPENDENCY_CLASS = 'Go\ParserReflection\Stub\NewInInitializerDependency';

    private string $closuresFileName;

    private string $initializersFileName;

    private Parser $parser;

    protected function setUp(): void
    {
        $this->closuresFileName     = $this->resolveStubFile(self::CLOSURES_STUB_FILE);
        $this->initializersFileName = $this->resolveStubFile(self::INITIALIZERS_STUB_FILE);
        $this->parser               = (new ParserFactory())->createForNewestSupportedVersion();

        ReflectionEngine::init($this->createStubLocator([
            self::CLOSURES_STUB_CLASS           => $this->closuresFileName,
            self::ATTRIBUTE_STUB_CLASS          => $this->closuresFileName,
            self::INITIALIZERS_STUB_CLASS       => $this->initializersFileName,
            self::INITIALIZERS_DEPENDENCY_CLASS => $this->initializersFileName,
        ]));
    }

    protected function tearDown(): void
    {
        // Restores the default locator for the following tests, because this one replaces it
        ReflectionEngine::init(new ComposerLocator());
    }

    public function testGlobalConstantsWithClosuresAreResolved(): void
    {
        $constants = $this->getStubFileNamespace()->getConstants();

        $this->assertArrayHasKey('CLOSURE_CONST', $constants);
        $this->assertInstanceOf(\Closure::class, $constants['CLOSURE_CONST']);
        $this->assertSame(10, ($constants['CLOSURE_CONST'])(5));

        $this->assertArrayHasKey('ARROW_FUNCTION_CONST', $constants);
        $this->assertInstanceOf(\Closure::class, $constants['ARROW_FUNCTION_CONST']);
        $this->assertSame(15, ($constants['ARROW_FUNCTION_CONST'])(5));

        // Sibling constants should not be poisoned by the presence of closures in the same file
        $this->assertSame('plain', $constants['PLAIN_CONST']);
    }

    public function testGlobalFirstClassCallableConstantsAreResolved(): void
    {
        $constants = $this->getStubFileNamespace()->getConstants();

        $this->assertInstanceOf(\Closure::class, $constants['FCC_CONST']);
        $this->assertSame(6, ($constants['FCC_CONST'])('foobar'));

        $this->assertInstanceOf(\Closure::class, $constants['UNQUALIFIED_FCC_CONST']);
        $this->assertSame('ABC', ($constants['UNQUALIFIED_FCC_CONST'])('abc'));
    }

    public function testClassConstantsWithClosuresAreResolved(): void
    {
        $parsedClass = $this->getStubFileNamespace()->getClass(self::CLOSURES_STUB_CLASS);
        $constants   = $parsedClass->getConstants();

        $this->assertInstanceOf(\Closure::class, $constants['CALLBACK']);
        $this->assertSame(2, ($constants['CALLBACK'])(1));

        $this->assertInstanceOf(\Closure::class, $constants['DOUBLER']);
        $this->assertSame(8, ($constants['DOUBLER'])(4));

        $this->assertInstanceOf(\Closure::class, $constants['UPPERCASE']);
        $this->assertSame('ABC', ($constants['UPPERCASE'])('abc'));

        $this->assertSame('simple', $constants['PLAIN']);
        $this->assertFalse(class_exists(self::CLOSURES_STUB_CLASS, false), 'Stub class should not be loaded');
    }

    public function testParameterDefaultValueWithClosureIsResolved(): void
    {
        $parsedClass  = $this->getStubFileNamespace()->getClass(self::CLOSURES_STUB_CLASS);
        $parsedMethod = $parsedClass->getMethod('methodWithClosureDefault');
        $parameter    = $parsedMethod->getParameters()[0];

        $this->assertTrue($parameter->isDefaultValueAvailable());
        $defaultValue = $parameter->getDefaultValue();
        $this->assertInstanceOf(\Closure::class, $defaultValue);
        $this->assertSame(1, $defaultValue());

        $expression = $parameter->getDefaultValueExpression();
        $this->assertNotNull($expression);
        $this->assertStringContainsString('static function', $expression);
    }

    public function testParameterDefaultValueWithArrowFunctionIsResolved(): void
    {
        $parsedClass  = $this->getStubFileNamespace()->getClass(self::CLOSURES_STUB_CLASS);
        $parsedMethod = $parsedClass->getMethod('methodWithArrowFunctionDefault');
        $parameter    = $parsedMethod->getParameters()[0];

        $defaultValue = $parameter->getDefaultValue();
        $this->assertInstanceOf(\Closure::class, $defaultValue);
        $this->assertSame(12, $defaultValue(4));
        $this->assertStringContainsString('static fn', (string) $parameter->getDefaultValueExpression());
    }

    public function testPropertyDefaultValueWithArrowFunctionIsResolved(): void
    {
        $parsedClass    = $this->getStubFileNamespace()->getClass(self::CLOSURES_STUB_CLASS);
        $parsedProperty = $parsedClass->getProperty('handler');

        $defaultValue = $parsedProperty->getDefaultValue();
        $this->assertInstanceOf(\Closure::class, $defaultValue);
        $this->assertSame('trimmed', $defaultValue('  trimmed  '));
    }

    public function testAttributeArgumentWithArrowFunctionIsResolved(): void
    {
        $parsedClass = $this->getStubFileNamespace()->getClass(self::CLOSURES_STUB_CLASS);
        $attributes  = $parsedClass->getAttributes();

        $this->assertCount(1, $attributes);
        $this->assertSame(self::ATTRIBUTE_STUB_CLASS, $attributes[0]->getName());

        $arguments = $attributes[0]->getArguments();
        $this->assertInstanceOf(\Closure::class, $arguments[0]);
        $this->assertSame(25, ($arguments[0])(5));
    }

    public function testResolvedClosureIsStaticAndHasNoScope(): void
    {
        $constants = $this->getStubFileNamespace()->getConstants();

        $closureReflection = new \ReflectionFunction($constants['CLOSURE_CONST']);
        $this->assertNull($closureReflection->getClosureThis());
        $this->assertNull($closureReflection->getClosureScopeClass());
    }

    public function testClosureWithCapturedVariablesIsNotResolved(): void
    {
        $this->expectException(ReflectionException::class);
        $this->expectExceptionMessageMatches('/captured variables/');

        $nodes            = $this->parser->parse('<?php function ($x) use ($y) { return $y; };');
        $expressionSolver = new NodeExpressionResolver(null);
        $expressionSolver->process($nodes[0]);
    }

    public function testFirstClassCallableForUnknownFunctionThrowsDescriptiveException(): void
    {
        $nodes            = $this->parser->parse('<?php \\Go\\ParserReflection\\Stub\\thisFunctionDoesNotExist(...);');
        $expressionSolver = new NodeExpressionResolver(null);

        try {
            $expressionSolver->process($nodes[0]);
            $this->fail('Expected ReflectionException was not thrown');
        } catch (ReflectionException $exception) {
            $this->assertStringNotContainsString('Could not find handler', $exception->getMessage());
            $this->assertMatchesRegularExpression('/is not defined/', $exception->getMessage());
        }
    }

    public function testNewInInitializerDoesNotTriggerAutoloading(): void
    {
        $this->assertFalse(
            class_exists(self::INITIALIZERS_DEPENDENCY_CLASS, false),
            'Dependency class should not be loaded before the test'
        );

        $autoloadedClasses = [];
        $autoloadSpy       = static function (string $className) use (&$autoloadedClasses): void {
            $autoloadedClasses[] = $className;
        };
        spl_autoload_register($autoloadSpy);

        try {
            $reflectionFile      = new ReflectionFile($this->initializersFileName);
            $reflectionNamespace = $reflectionFile->getFileNamespace(self::STUB_NAMESPACE);
            $parsedMethod        = $reflectionNamespace->getClass(self::INITIALIZERS_STUB_CLASS)
                ->getMethod('withDependency');

            $defaultValue = $parsedMethod->getParameters()[0]->getDefaultValue();
        } finally {
            spl_autoload_unregister($autoloadSpy);
        }

        $this->assertIsObject($defaultValue);
        $this->assertSame(self::INITIALIZERS_DEPENDENCY_CLASS, $defaultValue::class);
        $this->assertSame('injected', $defaultValue->label);
        $this->assertNotContains(
            self::INITIALIZERS_DEPENDENCY_CLASS,
            $autoloadedClasses,
            'Instantiation of a class in the initializer should not trigger the autoloader'
        );
    }

    public function testNewExpressionForUnknownClassThrowsDescriptiveException(): void
    {
        $this->expectException(ReflectionException::class);
        $this->expectExceptionMessageMatches('/can not be found by the registered locator/');

        $nodes            = $this->parser->parse('<?php new \\Go\\ParserReflection\\Stub\\ThisClassDoesNotExist();');
        $expressionSolver = new NodeExpressionResolver(null);
        $expressionSolver->process($nodes[0]);
    }

    private function getStubFileNamespace(): ReflectionFileNamespace
    {
        return (new ReflectionFile($this->closuresFileName))->getFileNamespace(self::STUB_NAMESPACE);
    }

    /**
     * @param array<string, string> $classMap
     */
    private function createStubLocator(array $classMap): CallableLocator
    {
        $composerLocator = new ComposerLocator();

        return new CallableLocator(
            static fn (string $className): false|string
                => $classMap[$className] ?? $composerLocator->locateClass($className)
        );
    }

    private function resolveStubFile(string $stubFileName): string
    {
        $resolvedFileName = stream_resolve_include_path(__DIR__ . $stubFileName);
        $this->assertIsString($resolvedFileName, "Stub file {$stubFileName} should be available");

        return $resolvedFileName;
    }
}
