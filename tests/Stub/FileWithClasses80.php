<?php
/**
 * Parser Reflection API
 *
 * @copyright Copyright 2022, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 *
 * @noinspection PhpUnused
 * @noinspection PhpIllegalPsrClassPathInspection
 * @noinspection PhpMissingReturnTypeInspection
 * @noinspection PhpPropertyOnlyWrittenInspection
 */
declare(strict_types=1);

namespace Go\ParserReflection\Stub;

// PHP 8.0+ code

class ClassWithUnionTypes
{
    public function unionType(int|string $value) {}
    public function unionTypeWithNull(int|string|null $value) {}
    public function unionTypeWithDefault(int|string $value = 42) {}
}

class ClassWithConstructorPropertyPromotion
{
    public function __construct(
        public int $id,
        protected string $name,
        private ?string $description = null,
    ) {}
}
