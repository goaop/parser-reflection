<?php
declare(strict_types=1);
/**
 * Parser Reflection API
 *
 * @copyright Copyright 2026, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\ParserReflection;

use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Parity tests for ReflectionEnum against native \ReflectionEnum
 *
 * @see ReflectionEnum
 */
class ReflectionEnumTest extends AbstractTestCase
{
    /**
     * Name of the reflection class to test
     */
    protected static string $reflectionClassToTest = \ReflectionEnum::class;

    /**
     * Default stub file containing enum declarations
     */
    protected const DEFAULT_STUB_FILENAME = '/Stub/FileWithClasses81.php';

    /**
     * Override to only analyse files that contain enum declarations
     */
    public static function getFilesToAnalyze(): \Generator
    {
        yield 'PHP8.1' => [__DIR__ . '/Stub/FileWithClasses81.php'];
    }

    /**
     * Provides [ReflectionEnum, \ReflectionEnum] pairs for all enums found in stub files
     */
    public static function enumsDataProvider(): \Generator
    {
        foreach (static::getFileNamespacesToAnalyze() as $prefix => [$reflectionFile, $fileNamespace]) {
            foreach ($fileNamespace->getEnums() as $parsedEnum) {
                $refEnum = new \ReflectionEnum($parsedEnum->getName());
                yield $prefix . ' ' . $refEnum->getName() => [$parsedEnum, $refEnum];
            }
        }
    }

    /**
     * Override classesDataProvider so inherited AbstractTestCase helpers receive enum pairs
     */
    public static function classesDataProvider(): \Generator
    {
        return static::enumsDataProvider();
    }

    /**
     * Provides [ReflectionEnum, \ReflectionEnum, getter] triples for parity testing
     */
    public static function reflectionGetterDataProvider(): \Generator
    {
        $allGetters = static::getGettersToCheck();
        foreach (static::enumsDataProvider() as $prefix => [$parsedEnum, $refEnum]) {
            foreach ($allGetters as $getterName) {
                yield $prefix . ', ' . $getterName => [$parsedEnum, $refEnum, $getterName];
            }
        }
    }

    /**
     * Performs method-by-method comparison with native \ReflectionEnum
     */
    #[DataProvider('reflectionGetterDataProvider')]
    public function testReflectionGetterParity(
        ReflectionEnum $parsedEnum,
        \ReflectionEnum $refEnum,
        string $getterName
    ): void {
        $enumName      = $parsedEnum->getName();
        $expectedValue = $refEnum->$getterName();
        $actualValue   = $parsedEnum->$getterName();

        // __toString output format differences are acceptable
        if ($expectedValue !== $actualValue && $getterName === '__toString') {
            $this->markTestSkipped("__toString for enum {$enumName} is not equal:\n{$expectedValue}{$actualValue}");
        }
        // Enum case constants cannot be resolved statically from AST
        if ($getterName === 'getConstants') {
            $this->markTestSkipped(
                "getConstants for enum {$enumName} couldn't be resolved fully from AST.\n" .
                "See https://github.com/goaop/parser-reflection/issues/132"
            );
        }

        $this->assertSame(
            $expectedValue,
            $actualValue,
            "{$getterName}() for enum {$enumName} should be equal"
        );
    }

    /**
     * Tests that isBacked() matches native reflection for all enums
     */
    #[DataProvider('enumsDataProvider')]
    public function testIsBacked(
        ReflectionEnum $parsedEnum,
        \ReflectionEnum $refEnum
    ): void {
        $this->assertSame(
            $refEnum->isBacked(),
            $parsedEnum->isBacked(),
            "isBacked() for {$parsedEnum->getName()} should match native reflection"
        );
    }

    /**
     * Tests that getBackingType() matches native reflection for all enums
     */
    #[DataProvider('enumsDataProvider')]
    public function testGetBackingType(
        ReflectionEnum $parsedEnum,
        \ReflectionEnum $refEnum
    ): void {
        $expectedType = $refEnum->getBackingType();
        $actualType   = $parsedEnum->getBackingType();

        if ($expectedType === null) {
            $this->assertNull(
                $actualType,
                "Backing type for {$parsedEnum->getName()} should be null"
            );
        } else {
            $this->assertNotNull(
                $actualType,
                "Backing type for {$parsedEnum->getName()} should not be null"
            );
            $this->assertSame(
                (string) $expectedType,
                (string) $actualType,
                "Backing type for {$parsedEnum->getName()} should match"
            );
        }
    }

    /**
     * Tests that getCases() returns the correct number of cases and that each case name matches
     */
    #[DataProvider('enumsDataProvider')]
    public function testGetCases(
        ReflectionEnum $parsedEnum,
        \ReflectionEnum $refEnum
    ): void {
        $parsedCases   = $parsedEnum->getCases();
        $originalCases = $refEnum->getCases();

        $this->assertCount(
            count($originalCases),
            $parsedCases,
            "Number of cases in {$parsedEnum->getName()} should match"
        );

        foreach ($originalCases as $originalCase) {
            $caseName = $originalCase->getName();
            $this->assertTrue(
                $parsedEnum->hasCase($caseName),
                "Case {$caseName} should exist in {$parsedEnum->getName()}"
            );
            $parsedCase = $parsedEnum->getCase($caseName);
            $this->assertSame(
                $caseName,
                $parsedCase->getName(),
                "Case name for {$caseName} in {$parsedEnum->getName()} should match"
            );
        }
    }

    /**
     * Tests hasCase() and getCase() against native reflection
     */
    #[DataProvider('enumsDataProvider')]
    public function testHasCase(
        ReflectionEnum $parsedEnum,
        \ReflectionEnum $refEnum
    ): void {
        $this->assertFalse(
            $parsedEnum->hasCase('NonExistentCaseXYZ'),
            "hasCase() should return false for non-existent case"
        );

        foreach ($refEnum->getCases() as $originalCase) {
            $caseName = $originalCase->getName();
            $this->assertTrue(
                $parsedEnum->hasCase($caseName),
                "hasCase({$caseName}) should return true for {$parsedEnum->getName()}"
            );
        }
    }

    /**
     * Returns list of no-arg getter methods that can be compared directly
     */
    protected static function getGettersToCheck(): array
    {
        return [
            'getFileName', 'getStartLine', 'getEndLine', 'getDocComment', 'getExtension', 'getExtensionName',
            'getName', 'getNamespaceName', 'getShortName', 'inNamespace',
            'isAbstract', 'isFinal', 'isInterface', 'isInternal', 'isTrait', 'isUserDefined',
            'getTraitNames', 'getInterfaceNames', 'getStaticProperties', 'getDefaultProperties',
            'getTraitAliases', 'isEnum', 'isBacked',
        ];
    }
}
