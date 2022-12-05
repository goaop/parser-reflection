<?php
/**
 * Parser Reflection API
 *
 * @copyright Copyright 2022, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 *
 * @noinspection PhpDocMissingThrowsInspection
 * @noinspection PhpUnhandledExceptionInspection
 */
declare(strict_types=1);

namespace Go\ParserReflection;

use Go\ParserReflection\Stub\AttributeWithOverriddenConstructor;
use ReflectionAttribute as BaseReflectionAttribute;
use ReflectionClass as BaseReflectionClass;
use ReflectionFunction as BaseReflectionFunction;
use Reflector;

class ReflectionAttributeTest extends AbstractTestCase
{
    /**
     * Name of the class to compare
     *
     * @var string
     */
    protected static string $reflectionClassToTest = BaseReflectionAttribute::class;

    /**
     * Provides a list of files for analysis
     *
     * @return array
     */
    public function getFilesToAnalyze(): array
    {
        $files = [];

        if (PHP_VERSION_ID >= 80000) {
            $files['PHP8.0'] = [
                __DIR__ . '/Stub/FileWithClasses80.php',
                __DIR__ . '/Stub/FileWithAttributes80.php'
            ];
        }

        return $files;
    }

    /**
     * Performs method-by-method comparison with original reflection
     *
     * @dataProvider caseProvider
     *
     * @param Reflector               $parsedReflection Parsed reflection
     * @param BaseReflectionAttribute $refAttribute     Attribute to analyze
     * @param string                  $getterName       Name of the reflection method to test
     * @param int                     $index
     */
    public function testReflectionAttributeParity(
        Reflector $parsedReflection,
        BaseReflectionAttribute $refAttribute,
        string $getterName,
        int $index
    ): void {
        /** @var ReflectionAttribute $parsedAttribute */
        $parsedAttribute = $parsedReflection->getAttributes()[$index];
        $reflectorName   = $parsedReflection->getName();
        $attributeName   = $refAttribute->getName();

        if ($reflectorName === AttributeWithOverriddenConstructor::class
            && $getterName === 'isRepeated'
        ) {
            $this->markTestIncomplete('isRepeated() needs to be parsed more carefully');
        }

        $expectedValue = $refAttribute->$getterName();
        $actualValue   = $parsedAttribute->$getterName();
        $this->assertSame(
            print_r($expectedValue, true),
            print_r($actualValue, true),
            "$getterName() for attribute $reflectorName->$attributeName should be equal"
        );
    }

    /**
     * Provides full test-cases list
     *
     * @return array
     */
    public function caseProvider(): array
    {
        $allNameGetters = $this->getGettersToCheck();

        $testCases = [];
        $files = $this->getFilesToAnalyze();
        foreach ($files as $fileList) {
            foreach ($fileList as $fileName) {
                $fileName = stream_resolve_include_path($fileName);
                $fileNode = ReflectionEngine::parseFile($fileName);

                $reflectionFile = new ReflectionFile($fileName, $fileNode);
                include_once $fileName;
                foreach ($reflectionFile->getFileNamespaces() as $fileNamespace) {
                    // Classes
                    foreach ($fileNamespace->getClasses() as $parsedClass) {
                        $refClass = new BaseReflectionClass($parsedClass->getName());

                        $this->assertCount(
                            count($refClass->getAttributes()),
                            $parsedClass->getAttributes(),
                        );

                        foreach ($refClass->getAttributes() as $index => $attribute) {
                            $caseName = $parsedClass->getShortName()
                                . ', #[' . $this->getShortName($attribute->getName()) . ']';
                            foreach ($allNameGetters as $getterName) {
                                $testCases[$caseName . ', ' . $getterName] = [
                                    $parsedClass,
                                    $attribute,
                                    $getterName,
                                    $index,
                                ];
                            }
                        }

                        // Methods
                        foreach ($parsedClass->getMethods() as $parsedMethod) {
                            $refMethod = $refClass->getMethod($parsedMethod->getName());

                            $this->assertCount(
                                count($refMethod->getAttributes()),
                                $parsedMethod->getAttributes(),
                            );

                            foreach ($refMethod->getAttributes() as $index => $attribute) {
                                $caseName = $parsedClass->getShortName() . '::' . $parsedMethod->getName()
                                    . ', #[' . $this->getShortName($attribute->getName()) . ']';
                                foreach ($allNameGetters as $getterName) {
                                    $testCases[$caseName . ', ' . $getterName] = [
                                        $parsedMethod,
                                        $attribute,
                                        $getterName,
                                        $index,
                                    ];
                                }
                            }

                            // Parameters
                            foreach ($parsedMethod->getParameters() as $parsedParameter) {
                                $refParameter = $refMethod->getParameters()[$parsedParameter->getPosition()];

                                $this->assertCount(
                                    count($refMethod->getAttributes()),
                                    $parsedMethod->getAttributes(),
                                );

                                foreach ($refParameter->getAttributes() as $index => $attribute) {
                                    $caseName = $parsedClass->getShortName() . '::' . $parsedMethod->getName()
                                        . '() -> $' . $parsedParameter->getName()
                                        . ', #[' . $this->getShortName($attribute->getName()) . ']';
                                    foreach ($allNameGetters as $getterName) {
                                        $testCases[$caseName . ', ' . $getterName] = [
                                            $parsedParameter,
                                            $attribute,
                                            $getterName,
                                            $index,
                                        ];
                                    }
                                }
                            }
                        }

                        // Properties
                        foreach ($parsedClass->getProperties() as $parsedProperty) {
                            $refProperty = $refClass->getProperty($parsedProperty->getName());

                            $this->assertCount(
                                count($refProperty->getAttributes()),
                                $parsedProperty->getAttributes(),
                            );

                            foreach ($refProperty->getAttributes() as $index => $attribute) {
                                $caseName = $parsedClass->getShortName() . '::$' . $parsedProperty->getName()
                                    . ', #[' . $this->getShortName($attribute->getName()) . ']';
                                foreach ($allNameGetters as $getterName) {
                                    $testCases[$caseName . ', ' . $getterName] = [
                                        $parsedProperty,
                                        $attribute,
                                        $getterName,
                                        $index,
                                    ];
                                }
                            }
                        }

                        // Constants
                        foreach ($parsedClass->getReflectionConstants() as $parsedConstant) {
                            $constantName = $parsedConstant->getName();
                            $refConstant = $refClass->getReflectionConstant($constantName);

                            $this->assertCount(
                                count($refConstant->getAttributes()),
                                $parsedConstant->getAttributes(),
                            );

                            foreach ($refConstant->getAttributes() as $index => $attribute) {
                                $caseName = $parsedClass->getShortName() . '::' . $constantName
                                    . ', #[' . $this->getShortName($attribute->getName()) . ']';
                                foreach ($allNameGetters as $getterName) {
                                    $testCases[$caseName . ', ' . $getterName] = [
                                        $parsedConstant,
                                        $attribute,
                                        $getterName,
                                        $index,
                                    ];
                                }
                            }
                        }
                    }

                    // Functions
                    foreach ($fileNamespace->getFunctions() as $parsedFunction) {
                        $refFunction = new BaseReflectionFunction($parsedFunction->getName());

                        $this->assertCount(
                            count($refFunction->getAttributes()),
                            $parsedFunction->getAttributes(),
                        );

                        foreach ($refFunction->getAttributes() as $index => $attribute) {
                            $caseName = $parsedFunction->getName()
                                . ', #[' . $this->getShortName($attribute->getName()) . ']';
                            foreach ($allNameGetters as $getterName) {
                                $testCases[$caseName . ', ' . $getterName] = [
                                    $parsedFunction,
                                    $attribute,
                                    $getterName,
                                    $index,
                                ];
                            }
                        }
                    }
                }
            }
        }

        return $testCases;
    }

    private function getShortName(string $name): string
    {
        $parts = explode('\\', $name);
        return array_pop($parts);
    }

    /**
     * Returns list of ReflectionAttribute getters that should be tested
     *
     * @return array
     */
    protected function getGettersToCheck(): array
    {
        $allNameGetters = [
            'getName', 'newInstance', 'getArguments', 'getTarget', 'isRepeated'
        ];

        return $allNameGetters;
    }
}
