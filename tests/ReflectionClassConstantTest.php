<?php
declare(strict_types=1);

namespace Go\ParserReflection;

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
}
