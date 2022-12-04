<?php
/**
 * Parser Reflection API
 *
 * @copyright Copyright 2022, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */
namespace Go\ParserReflection;

use ReflectionUnionType as BaseReflectionUnionType;

/**
 * ReflectionUnionType implementation
 */
class ReflectionUnionType extends BaseReflectionUnionType
{
    /**
     * Initializes reflection data
     *
     * @param ReflectionNamedType[] $types      List of types
     * @param bool                  $allowsNull If type allows null or not
     */
    public function __construct(
        private array $types,
        private bool $allowsNull,
    ) {}

    /**
     * Get list of named types of union type
     *
     * @return ReflectionNamedType[]
     */
    public function getTypes(): array
    {
        return $this->types;
    }

    /**
     * Checks if null is allowed
     *
     * @link https://php.net/manual/en/reflectiontype.allowsnull.php
     *
     * @return bool Returns {@see true} if {@see null} is allowed, otherwise {@see false}
     */
    public function allowsNull(): bool
    {
        return $this->allowsNull;
    }
}
