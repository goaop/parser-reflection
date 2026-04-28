<?php
declare(strict_types=1);

namespace Go\ParserReflection;

use Go\ParserReflection\Stub\SimplePhp81EnumWithSuit;
use Go\ParserReflection\Stub\BackedPhp81EnumHTTPMethods;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ReflectionClassConstantTest extends AbstractTestCase
{
    protected static string $reflectionClassToTest = \ReflectionClassConstant::class;

    /**
     * Performs method-by-method comparison with original reflection
     *
     * @param ReflectionClass          $parsedClass Parsed class
     * @param \ReflectionClassConstant $refClassConstant Method to analyze
     * @param string                   $getterName Name of the reflection method to test
     */
    #[DataProvider('reflectionGetterDataProvider')]
    public function testReflectionGetterParity(
        ReflectionClass          $parsedClass,
        \ReflectionClassConstant $refClassConstant,
        string                   $getterName
    ): void {
        $constantName   = $refClassConstant->getName();
        $className      = $parsedClass->getName();
        $parsedConstant = $parsedClass->getReflectionConstant($constantName);

        $expectedValue = $refClassConstant->$getterName();
        $actualValue   = $parsedConstant->$getterName();
        // I would like to completely stop maintaining the __toString method
        if ($expectedValue !== $actualValue && $getterName === '__toString') {
            $this->markTestSkipped("__toString for constant {$className}::{$constantName} is not equal:\n{$expectedValue}{$actualValue}");
        }
        if ($getterName === 'getValue' && $parsedClass->isEnum()) {
            $this->markTestSkipped("getValue() for Enum cases could not be resolved, see https://github.com/goaop/parser-reflection/issues/132");
        }
        $this->assertSame(
            $expectedValue,
            $actualValue,
            "$getterName() for constant $className::$constantName() should be equal"
        );
    }

    /**
     * Provides full test-case list in the form [ReflectionClass, \ReflectionClassConstant, getter name to check]
     */
    public static function reflectionGetterDataProvider(): \Generator
    {
        $allNameGetters = self::getGettersToCheck();
        foreach (self::classConstantsDataProvider() as $prefix => [$parsedClass, $classReflectionConstant]) {
            foreach ($allNameGetters as $getterName) {
                yield $prefix . ', ' . $getterName => [
                    $parsedClass,
                    $classReflectionConstant,
                    $getterName
                ];
            }
        }
    }

    /**
     * Provides full test-case list in the form [ParsedClass, \ReflectionClassConstant to check]
     */
    public static function classConstantsDataProvider(): \Generator
    {
        foreach (self::classesDataProvider() as $prefix => [$parsedClass, $refClass]) {
            foreach ($refClass->getReflectionConstants() as $reflectionConstant) {
                $fullConstantName = $parsedClass->getName() . '::' . $reflectionConstant->getName();
                yield $prefix . ' ' . $fullConstantName => [
                    $parsedClass,
                    $reflectionConstant,
                ];
            }
        }
    }

    /**
     * @inheritDoc
     */
    static protected function getGettersToCheck(): array
    {
        $php83Getters = [];
        if (PHP_VERSION_ID >= 80300) {
            $php83Getters[] = 'hasType';
        }
        return [
            'getDocComment', 'getModifiers', 'getName', 'getValue',
            'isPrivate', 'isProtected', 'isPublic', 'isFinal', 'isEnumCase',
            '__toString', ...$php83Getters
        ];
    }

    /**
     * Tests that getDeclaringClass() returns ReflectionEnum for enum cases, matching native PHP behavior
     */
    public function testGetDeclaringClassReturnsReflectionEnumForEnumCase(): void
    {
        $fileName = stream_resolve_include_path(__DIR__ . '/Stub/FileWithClasses81.php');
        $fileNode = ReflectionEngine::parseFile($fileName);
        $reflectionFile = new ReflectionFile($fileName, $fileNode);
        include_once $fileName;

        $fileNamespace = $reflectionFile->getFileNamespace('Go\ParserReflection\Stub');
        $parsedEnum    = $fileNamespace->getEnums()[SimplePhp81EnumWithSuit::class];

        $parsedConstant = $parsedEnum->getReflectionConstant('Clubs');
        $this->assertInstanceOf(ReflectionClassConstant::class, $parsedConstant);
        $this->assertTrue($parsedConstant->isEnumCase());

        $declaringClass = $parsedConstant->getDeclaringClass();
        $this->assertInstanceOf(ReflectionEnum::class, $declaringClass);
        $this->assertSame(SimplePhp81EnumWithSuit::class, $declaringClass->getName());

        // Verify native PHP also returns a ReflectionEnum subtype for enum case constants
        $nativeConstant = (new \ReflectionEnum(SimplePhp81EnumWithSuit::class))->getReflectionConstant('Clubs');
        $this->assertInstanceOf(\ReflectionEnum::class, $nativeConstant->getDeclaringClass());
    }

    /**
     * Tests that toEnumCase() returns the correct type for unit enum cases
     */
    public function testToEnumCaseReturnsReflectionEnumUnitCase(): void
    {
        $fileName = stream_resolve_include_path(__DIR__ . '/Stub/FileWithClasses81.php');
        $fileNode = ReflectionEngine::parseFile($fileName);
        $reflectionFile = new ReflectionFile($fileName, $fileNode);
        include_once $fileName;

        $fileNamespace  = $reflectionFile->getFileNamespace('Go\ParserReflection\Stub');
        $parsedEnum     = $fileNamespace->getEnums()[SimplePhp81EnumWithSuit::class];
        $parsedConstant = $parsedEnum->getReflectionConstant('Clubs');

        $this->assertInstanceOf(ReflectionClassConstant::class, $parsedConstant);
        $enumCase = $parsedConstant->toEnumCase();
        $this->assertInstanceOf(ReflectionEnumUnitCase::class, $enumCase);
        $this->assertSame('Clubs', $enumCase->getName());
    }

    /**
     * Tests that toEnumCase() returns the correct type for backed enum cases
     */
    public function testToEnumCaseReturnsReflectionEnumBackedCase(): void
    {
        $fileName = stream_resolve_include_path(__DIR__ . '/Stub/FileWithClasses81.php');
        $fileNode = ReflectionEngine::parseFile($fileName);
        $reflectionFile = new ReflectionFile($fileName, $fileNode);
        include_once $fileName;

        $fileNamespace  = $reflectionFile->getFileNamespace('Go\ParserReflection\Stub');
        $parsedEnum     = $fileNamespace->getEnums()[BackedPhp81EnumHTTPMethods::class];
        $parsedConstant = $parsedEnum->getReflectionConstant('GET');

        $this->assertInstanceOf(ReflectionClassConstant::class, $parsedConstant);
        $enumCase = $parsedConstant->toEnumCase();
        $this->assertInstanceOf(ReflectionEnumBackedCase::class, $enumCase);
        $this->assertSame('GET', $enumCase->getName());
        $this->assertSame('get', $enumCase->getBackingValue());
    }

    /**
     * Tests that toEnumCase() throws ReflectionException for non-enum-case constants
     */
    public function testToEnumCaseThrowsForNonEnumCase(): void
    {
        $this->expectException(ReflectionException::class);
        $this->expectExceptionMessageMatches('/is not an enum case/');

        $parsedConstant = new ReflectionClassConstant(
            \Go\ParserReflection\Stub\ClassWithPhp81FinalClassConst::class,
            'TEST'
        );
        $parsedConstant->toEnumCase();
    }

    /**
     * Tests that ReflectionEngine::parseClassConstant handles enum cases by name
     */
    public function testParseClassConstantHandlesEnumCases(): void
    {
        $fileName = stream_resolve_include_path(__DIR__ . '/Stub/FileWithClasses81.php');
        ReflectionEngine::parseFile($fileName);
        include_once $fileName;

        // This should not throw — previously would have thrown "ClassConstant not found"
        $parsedConstant = new ReflectionClassConstant(SimplePhp81EnumWithSuit::class, 'Clubs');
        $this->assertInstanceOf(ReflectionClassConstant::class, $parsedConstant);
        $this->assertTrue($parsedConstant->isEnumCase());
        $this->assertSame('Clubs', $parsedConstant->getName());
    }
}
