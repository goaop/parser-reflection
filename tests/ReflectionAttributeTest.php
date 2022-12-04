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

use ReflectionAttribute as BaseReflectionAttribute;

class ReflectionAttributeTest extends AbstractTestCase
{
    /**
     * Name of the class to compare
     *
     * @var string
     */
    protected static string $reflectionClassToTest = BaseReflectionAttribute::class;

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