<?php
/**
 * Parser Reflection API
 *
 * @copyright Copyright 2015-2022, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */
declare(strict_types=1);

namespace Go\ParserReflection;

use ReflectionNamedType;
use ReflectionType as BaseReflectionType;

/**
 * ReflectionType implementation
 */
class ReflectionType extends BaseReflectionType
{
    /**
     * If type allows null or not
     *
     * @var bool
     */
    private bool $allowsNull;

    /**
     * Is type built-in or not
     *
     * @var bool
     */
    private bool $isBuiltin;

    /**
     * Type name
     *
     * @var string
     */
    private string $type;

    /**
     * Initializes reflection data
     *
     * @param string $type
     * @param bool   $allowsNull
     * @param bool   $isBuiltin
     */
    public function __construct(string $type, bool $allowsNull, bool $isBuiltin)
    {
        $this->type       = $type;
        $this->allowsNull = $allowsNull;
        $this->isBuiltin  = $isBuiltin;
    }

    /**
     * {@inheritDoc}
     */
    public function allowsNull(): bool
    {
        return $this->allowsNull;
    }

    /**
     * {@inheritDoc}
     */
    public function isBuiltin(): bool
    {
        return $this->isBuiltin;
    }

    /**
     * {@inheritDoc}
     */
    public function __toString(): string
    {
        return $this->type;
    }

    /**
     * PHP reflection has its own rules, so 'int' type will be displayed as 'integer', etc...
     *
     * @see https://3v4l.org/nZFiT
     *
     * @param BaseReflectionType $type Type to display
     *
     * @return string
     */
    public static function convertToDisplayType(BaseReflectionType $type): string
    {
        if ($type instanceof ReflectionNamedType) {
            $displayType = $type->getName();
        } else {
            $displayType = (string)$type;
        }

        $displayType = ltrim($displayType, '\\');

        if ($type->allowsNull()) {
            $displayType = '?' . $displayType;
        }

        return $displayType;
    }
}
