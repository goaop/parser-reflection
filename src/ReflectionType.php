<?php

declare(strict_types=1);
/**
 * Parser Reflection API
 *
 * @copyright Copyright 2015, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\ParserReflection;

use ReflectionNamedType;
use ReflectionType as BaseReflectionType;
use ReflectionUnionType;

/**
 * ReflectionType implementation
 * @see \Go\ParserReflection\ReflectionTypeTest
 */
class ReflectionType extends BaseReflectionType
{
    /**
     * If type allows null or not
     */
    private bool $allowsNull;

    /**
     * Type name
     */
    private string $type;

    /**
     * Initializes reflection data
     */
    public function __construct(string $type, bool $allowsNull)
    {
        $this->type       = $type;
        $this->allowsNull = $allowsNull;
    }

    /**
     * @inheritDoc
     */
    public function allowsNull(): bool
    {
        return $this->allowsNull;
    }

    /**
     * @inheritDoc
     */
    public function __toString(): string
    {
        return $this->type;
    }

    /**
     * PHP reflection has it's own rules, so 'int' type will be displayed as 'integer', etc...
     *
     * @see https://3v4l.org/nZFiT
     */
    public static function convertToDisplayType(BaseReflectionType $type): string
    {
        if ($type instanceof ReflectionNamedType) {
            $displayType = $type->getName();
        } else {
            $displayType = (string)$type;
        }

        $displayType = ltrim($displayType, '\\');

        $specialNullableTypes = in_array($displayType, ['mixed', 'null'], true);
        if ($type->allowsNull() && !$type instanceof ReflectionUnionType && !$specialNullableTypes) {
            $displayType = '?' . $displayType;
        }

        return $displayType;
    }
}
