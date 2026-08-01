<?php
declare(strict_types=1);

namespace Go\ParserReflection;

use Go\ParserReflection\Locator\CallableLocator;
use Go\ParserReflection\Locator\ComposerLocator;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use ReflectionAttribute as InternalReflectionAttribute;

class AttributeInstanceOfFilterTest extends TestCase
{
    private const STUB_FILE = __DIR__ . '/Stub/FileWithAttributeHierarchy80.php';

    private const STUB_NAMESPACE = 'Go\ParserReflection\Stub\AttributeHierarchy';

    protected function tearDown(): void
    {
        ReflectionEngine::init(new ComposerLocator());
    }

    /**
     * The whole hierarchy is resolved from the AST only, so none of the involved classes may be loaded
     */
    public function testInstanceOfFilterResolvesHierarchyWithoutLoadingClasses(): void
    {
        $this->setUpAstOnlyLocator();

        $parsedClass = new ReflectionClass(self::STUB_NAMESPACE . '\HookedClass');

        $childAttributes = $parsedClass->getAttributes(
            self::STUB_NAMESPACE . '\ChildAttribute',
            InternalReflectionAttribute::IS_INSTANCEOF
        );
        $this->assertCount(1, $childAttributes);
        $this->assertSame(self::STUB_NAMESPACE . '\ChildAttribute', $childAttributes[0]->getName());
        $this->assertSame(['class-level'], $childAttributes[0]->getArguments());

        $baseAttributes = $parsedClass->getAttributes(
            self::STUB_NAMESPACE . '\BaseAttribute',
            InternalReflectionAttribute::IS_INSTANCEOF
        );
        $this->assertCount(1, $baseAttributes);
        $this->assertSame(self::STUB_NAMESPACE . '\ChildAttribute', $baseAttributes[0]->getName());

        $this->assertCount(0, $parsedClass->getAttributes(self::STUB_NAMESPACE . '\BaseAttribute'));

        $parsedProperty       = $parsedClass->getProperty('hookedProperty');
        $inheritedAttributes = $parsedProperty->getAttributes(
            self::STUB_NAMESPACE . '\BaseAttribute',
            InternalReflectionAttribute::IS_INSTANCEOF
        );
        $this->assertCount(1, $inheritedAttributes);
        $this->assertSame(self::STUB_NAMESPACE . '\GrandChildAttribute', $inheritedAttributes[0]->getName());

        $parsedMethod = $parsedClass->getMethod('hookedMethod');
        $this->assertCount(1, $parsedMethod->getAttributes(
            self::STUB_NAMESPACE . '\MarkerInterface',
            InternalReflectionAttribute::IS_INSTANCEOF
        ));
        $this->assertCount(1, $parsedMethod->getAttributes(
            self::STUB_NAMESPACE . '\ExtendedMarkerInterface',
            InternalReflectionAttribute::IS_INSTANCEOF
        ));
        $this->assertCount(0, $parsedMethod->getAttributes(
            self::STUB_NAMESPACE . '\BaseAttribute',
            InternalReflectionAttribute::IS_INSTANCEOF
        ));

        $this->assertStubHierarchyIsNotLoaded();
    }

    public function testInstanceOfFilterIgnoresUnknownAttributeClasses(): void
    {
        $parsedFile  = new ReflectionFile(self::STUB_FILE);
        $parsedClass = $parsedFile
            ->getFileNamespace(self::STUB_NAMESPACE)
            ->getClass(self::STUB_NAMESPACE . '\HookedClass');

        ReflectionEngine::init(new CallableLocator(fn(string $className): false|string => false));

        $this->assertCount(0, $parsedClass->getAttributes(
            self::STUB_NAMESPACE . '\BaseAttribute',
            InternalReflectionAttribute::IS_INSTANCEOF
        ));
        $this->assertCount(2, $parsedClass->getAttributes());

        $this->assertStubHierarchyIsNotLoaded();
    }

    public function testInvalidFilterFlagThrowsValueError(): void
    {
        $this->setUpAstOnlyLocator();

        $parsedClass = new ReflectionClass(self::STUB_NAMESPACE . '\HookedClass');

        $this->expectException(\ValueError::class);
        $this->expectExceptionMessage(
            'ReflectionClass::getAttributes(): Argument #2 ($flags) must be a valid attribute filter flag'
        );

        $parsedClass->getAttributes(self::STUB_NAMESPACE . '\BaseAttribute', 3);
    }

    public function testInvalidFilterFlagThrowsValueErrorForFunctionLike(): void
    {
        $this->setUpAstOnlyLocator();

        $parsedMethod = (new ReflectionClass(self::STUB_NAMESPACE . '\HookedClass'))->getMethod('hookedMethod');

        $this->expectException(\ValueError::class);
        $this->expectExceptionMessage(
            'ReflectionFunctionAbstract::getAttributes(): Argument #2 ($flags) must be a valid attribute filter flag'
        );

        $parsedMethod->getAttributes(self::STUB_NAMESPACE . '\BaseAttribute', 3);
    }

    /**
     * Runs isolated to keep the stub hierarchy unloaded for the AST-only test cases
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testInstanceOfFilterMatchesNativeReflectionForLoadedClasses(): void
    {
        require_once self::STUB_FILE;

        $className    = self::STUB_NAMESPACE . '\HookedClass';
        $parsedClass  = new ReflectionClass($className);
        $nativeClass  = new \ReflectionClass($className);
        $filterNames  = [
            self::STUB_NAMESPACE . '\BaseAttribute',
            self::STUB_NAMESPACE . '\ChildAttribute',
            self::STUB_NAMESPACE . '\GrandChildAttribute',
            self::STUB_NAMESPACE . '\MarkerInterface',
            self::STUB_NAMESPACE . '\ExtendedMarkerInterface',
            self::STUB_NAMESPACE . '\UnrelatedAttribute',
        ];

        foreach ($filterNames as $filterName) {
            $this->assertSame(
                $this->describeAttributes($nativeClass->getAttributes($filterName, InternalReflectionAttribute::IS_INSTANCEOF)),
                $this->describeAttributes($parsedClass->getAttributes($filterName, InternalReflectionAttribute::IS_INSTANCEOF)),
                "Class attributes filtered by $filterName"
            );

            $this->assertSame(
                $this->describeAttributes($nativeClass->getMethod('hookedMethod')->getAttributes($filterName, InternalReflectionAttribute::IS_INSTANCEOF)),
                $this->describeAttributes($parsedClass->getMethod('hookedMethod')->getAttributes($filterName, InternalReflectionAttribute::IS_INSTANCEOF)),
                "Method attributes filtered by $filterName"
            );

            $this->assertSame(
                $this->describeAttributes($nativeClass->getProperty('hookedProperty')->getAttributes($filterName, InternalReflectionAttribute::IS_INSTANCEOF)),
                $this->describeAttributes($parsedClass->getProperty('hookedProperty')->getAttributes($filterName, InternalReflectionAttribute::IS_INSTANCEOF)),
                "Property attributes filtered by $filterName"
            );

            $this->assertSame(
                $this->describeAttributes($nativeClass->getAttributes($filterName)),
                $this->describeAttributes($parsedClass->getAttributes($filterName)),
                "Class attributes filtered by exact name $filterName"
            );
        }

        $this->assertSame(
            $this->describeAttributes($nativeClass->getAttributes(null, InternalReflectionAttribute::IS_INSTANCEOF)),
            $this->describeAttributes($parsedClass->getAttributes(null, InternalReflectionAttribute::IS_INSTANCEOF)),
            'Unfiltered attributes are not affected by the flag'
        );

        // Unlike the engine, an unresolvable filter class never triggers autoloading and matches nothing
        $this->assertSame([], $parsedClass->getAttributes(
            self::STUB_NAMESPACE . '\MissingAttribute',
            InternalReflectionAttribute::IS_INSTANCEOF
        ));
    }

    /**
     * @param \ReflectionAttribute<object>[] $attributes
     *
     * @return array<int, array{string, array<int|string, mixed>}>
     */
    private function describeAttributes(array $attributes): array
    {
        $description = [];
        foreach ($attributes as $attribute) {
            $description[] = [$attribute->getName(), $attribute->getArguments()];
        }

        return $description;
    }

    private function setUpAstOnlyLocator(): void
    {
        ReflectionEngine::init(new CallableLocator(
            fn(string $className): false|string => str_starts_with($className, self::STUB_NAMESPACE . '\\')
                ? self::STUB_FILE
                : false
        ));
    }

    private function assertStubHierarchyIsNotLoaded(): void
    {
        foreach (['HookedClass', 'BaseAttribute', 'ChildAttribute', 'GrandChildAttribute', 'InterfaceAttribute'] as $shortName) {
            $this->assertFalse(
                class_exists(self::STUB_NAMESPACE . '\\' . $shortName, false),
                "Class $shortName must not be loaded by the AST-based instanceof resolution"
            );
        }

        foreach (['MarkerInterface', 'ExtendedMarkerInterface'] as $shortName) {
            $this->assertFalse(
                interface_exists(self::STUB_NAMESPACE . '\\' . $shortName, false),
                "Interface $shortName must not be loaded by the AST-based instanceof resolution"
            );
        }
    }
}
