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
 */
class ReflectionType extends BaseReflectionType
{
    /**
     * If type allows null or not
     *
     * @var bool
     */
    private $allowsNull;

    /**
     * Is type built-in or not
     *
     * @var
     */
    private $isBuiltin;

    /**
     * @var string Type name
     */
    private $type;

    /**
     * Initializes reflection data
     */
    public function __construct($type, $allowsNull, $isBuiltin)
    {
        $this->type       = $type;
        $this->allowsNull = $allowsNull;
        $this->isBuiltin  = $isBuiltin;
    }

    /**
     * @inheritDoc
     */
    public function allowsNull()
    {
        return $this->allowsNull;
    }

    /**
     * @inheritDoc
     */
    public function isBuiltin()
    {
        return $this->isBuiltin;
    }

    /**
     * @inheritDoc
     */
    public function __toString()
    {
        return $this->type;
    }

    /**
     * PHP reflection has it's own rules, so 'int' type will be displayed as 'integer', etc...
     *
     * @see https://3v4l.org/nZFiT
     *
     * @param BaseReflectionType $type Type to display
     *
     * @return string
     */
    public static function convertToDisplayType(BaseReflectionType $type)
    {
        if ($type instanceof ReflectionNamedType) {
            $displayType = $type->getName();
        } else {
            $displayType = (string)$type;
        }

        $displayType = ltrim($displayType, '\\');

        if ($type->allowsNull()) {
            if (! $type instanceof ReflectionUnionType) {
                $displayType = '?' . $displayType;
            } else {
                $displayType = '|null';
            }
        }

        return $displayType;
    }
}
