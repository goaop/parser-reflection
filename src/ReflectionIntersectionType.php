<?php

declare(strict_types=1);
/**
 * Parser Reflection API
 *
 * @copyright Copyright 2024, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\ParserReflection;

use ReflectionIntersectionType as BaseReflectionIntersectionType;

/**
 * ReflectionIntersectionType implementation
 */
class ReflectionIntersectionType extends BaseReflectionIntersectionType
{
    /**
     * @var ReflectionNamedType[]
     */
    private readonly array $types;

    /**
     * Initializes reflection data
     */
    public function __construct(ReflectionNamedType ...$types)
    {
        $this->types = $types;
    }

    /**
     * @inheritDoc
     */
    public function allowsNull(): bool
    {
        // @see https://php.watch/versions/8.1/intersection-types
        // Intersection Types only allow pure intersection types; composite types with nullable or Union Types are not
        // allowed, and results in a syntax error.
        //
        // Intersection Types only support class and interface names as intersection members.
        // Scalar types, array, void, mixed, callable, never, iterable, null, and other types are not allowed.
        return false;
    }

    /**
     * @inheritDoc
     */
    public function __toString(): string
    {
        $stringTypes = array_map(fn(\ReflectionNamedType $namedType) => (string) $namedType, $this->types);

        // Special iterable type is already union Traversable|array, thus should be replaced
        $iterableIndex = array_search('iterable', $stringTypes, true);
        if ($iterableIndex !== false) {
            unset($stringTypes[$iterableIndex]);
            array_push($stringTypes, 'Traversable', 'array');
        }

        return join('&', $stringTypes);
    }

    /**
     * @inheritDoc
     */
    public function getTypes(): array
    {
        return $this->types;
    }
}
