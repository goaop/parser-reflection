<?php
declare(strict_types=1);

namespace Go\ParserReflection;

use Go\ParserReflection\Locator\CallableLocator;
use Go\ParserReflection\Locator\ComposerLocator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Unqualified constants (true, false, null, global and namespaced constants) in the attribute arguments
 * of class members must be resolved in the namespace of the declaring class
 *
 * @see https://github.com/goaop/parser-reflection/issues/259
 */
class ConstantsInMemberAttributesTest extends TestCase
{
    private const STUB_FILE = __DIR__ . '/Stub/FileWithConstantsInMemberAttributes.php';

    private const STUB_NAMESPACE = 'Go\ParserReflection\Stub\MemberAttributeConstants';

    private const SUBJECT_CLASS = self::STUB_NAMESPACE . '\Subject';

    protected function setUp(): void
    {
        // The stub is resolved from the AST only, the locator knows nothing but this file
        ReflectionEngine::init(new CallableLocator(
            fn(string $className): false|string => str_starts_with($className, self::STUB_NAMESPACE . '\\')
                ? self::STUB_FILE
                : false
        ));
    }

    protected function tearDown(): void
    {
        ReflectionEngine::init(new ComposerLocator());
    }

    /**
     * @return iterable<string, array{string, mixed}>
     */
    public static function methodAttributeArgumentsProvider(): iterable
    {
        yield 'true'                    => ['withTrue', true];
        yield 'false'                   => ['withFalse', false];
        yield 'null'                    => ['withNull', null];
        yield 'uppercase TRUE'          => ['withUppercaseTrue', true];
        yield 'global constant'         => ['withGlobalConstant', PHP_INT_MAX];
        yield 'qualified true'          => ['withQualifiedTrue', true];
        yield 'class constant'          => ['withClassConstant', self::SUBJECT_CLASS];
        yield 'namespaced constant'     => ['withNamespacedConstant', 'class-namespace'];
        yield '__NAMESPACE__'           => ['withNamespaceMagicConstant', self::STUB_NAMESPACE];
        yield 'constants in array'      => ['withConstantsInArray', ['class-namespace', PHP_INT_MAX, null]];
    }

    /**
     * Methods imported from a trait resolve names in the namespace of the trait, not of the using class
     *
     * @return iterable<string, array{string, mixed}>
     */
    public static function traitMethodAttributeArgumentsProvider(): iterable
    {
        yield 'trait constant'      => ['fromTrait', 'trait-namespace'];
        yield 'trait __NAMESPACE__' => ['namespaceFromTrait', self::STUB_NAMESPACE . '\Traits'];
    }

    #[DataProvider('methodAttributeArgumentsProvider')]
    #[DataProvider('traitMethodAttributeArgumentsProvider')]
    public function testMethodReflectedThroughClass(string $methodName, mixed $expectedValue): void
    {
        $method = (new ReflectionClass(self::SUBJECT_CLASS))->getMethod($methodName);

        $this->assertSame([$expectedValue], $method->getAttributes()[0]->getArguments());
    }

    #[DataProvider('methodAttributeArgumentsProvider')]
    public function testMethodReflectedOnItsOwn(string $methodName, mixed $expectedValue): void
    {
        $method = new ReflectionMethod(self::SUBJECT_CLASS, $methodName);

        $this->assertSame([$expectedValue], $method->getAttributes()[0]->getArguments());
    }

    public function testClassAttribute(): void
    {
        $class = new ReflectionClass(self::SUBJECT_CLASS);

        $this->assertSame([true], $class->getAttributes()[0]->getArguments());
    }

    public function testClassConstantAttribute(): void
    {
        $constant = (new ReflectionClass(self::SUBJECT_CLASS))->getReflectionConstant('FLAGGED_CONSTANT');
        $this->assertInstanceOf(ReflectionClassConstant::class, $constant);

        $this->assertSame(['class-namespace'], $constant->getAttributes()[0]->getArguments());
    }

    public function testPropertyAttribute(): void
    {
        $property = (new ReflectionClass(self::SUBJECT_CLASS))->getProperty('flaggedProperty');

        $this->assertSame([PHP_INT_MAX], $property->getAttributes()[0]->getArguments());
    }

    public function testMethodParameterAttribute(): void
    {
        $parameter = (new ReflectionMethod(self::SUBJECT_CLASS, 'withFlaggedParameter'))->getParameters()[0];

        $this->assertSame(['class-namespace'], $parameter->getAttributes()[0]->getArguments());
        $this->assertSame(PHP_INT_MAX, $parameter->getDefaultValue());
    }

    public function testFunctionAndFunctionParameterAttributes(): void
    {
        $function = (new ReflectionFile(self::STUB_FILE))
            ->getFileNamespace(self::STUB_NAMESPACE)
            ->getFunction('flaggedFunction');
        $this->assertInstanceOf(ReflectionFunction::class, $function);

        $this->assertSame(['class-namespace'], $function->getAttributes()[0]->getArguments());
        $this->assertSame([null], $function->getParameters()[0]->getAttributes()[0]->getArguments());
    }

    /**
     * The same arguments must be reported by the native reflection once the stub is loaded
     */
    public function testParityWithNativeReflection(): void
    {
        $parsedClass = new ReflectionClass(self::SUBJECT_CLASS);
        $parsedFunction = (new ReflectionFile(self::STUB_FILE))
            ->getFileNamespace(self::STUB_NAMESPACE)
            ->getFunction('flaggedFunction');
        $this->assertInstanceOf(ReflectionFunction::class, $parsedFunction);

        // Collect everything before loading, so that the AST is the only source of the parsed values
        $parsedArguments = $this->collectAttributeArguments($parsedClass, $parsedFunction);

        require_once self::STUB_FILE;
        $nativeArguments = $this->collectAttributeArguments(
            new \ReflectionClass(self::SUBJECT_CLASS),
            new \ReflectionFunction(self::STUB_NAMESPACE . '\flaggedFunction')
        );

        $this->assertSame($nativeArguments, $parsedArguments);
    }

    /**
     * @param \ReflectionClass<object> $class
     *
     * @return array<string, list<mixed>>
     */
    private function collectAttributeArguments(\ReflectionClass $class, \ReflectionFunction $function): array
    {
        $arguments = [];
        $collect   = static function (string $key, \Reflector $reflector) use (&$arguments): void {
            foreach ($reflector->getAttributes() as $attribute) {
                $arguments[$key][] = $attribute->getArguments();
            }
        };

        $collect('class', $class);
        foreach ($class->getMethods() as $method) {
            $collect('method ' . $method->getName(), $method);
            foreach ($method->getParameters() as $parameter) {
                $collect('parameter ' . $method->getName() . '::$' . $parameter->getName(), $parameter);
            }
        }
        foreach ($class->getProperties() as $property) {
            $collect('property ' . $property->getName(), $property);
        }
        foreach ($class->getReflectionConstants() as $constant) {
            $collect('constant ' . $constant->getName(), $constant);
        }
        $collect('function', $function);
        foreach ($function->getParameters() as $parameter) {
            $collect('function parameter $' . $parameter->getName(), $parameter);
        }
        ksort($arguments);

        return $arguments;
    }
}
