<?php
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

/**
 * ReflectionType implementation
 */
class ReflectionType extends BaseReflectionType
{
    /**
     * If type allows null or not
     *
     * @var boolean
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
     * @param ReflectionType $type Type to display
     *
     * @return string
     */
    public static function convertToDisplayType(\ReflectionType $type)
    {
        static $typeMap = [
            'int'    => 'integer',
            'bool'   => 'boolean',
            'double' => 'float',
        ];

        if ($type instanceof ReflectionNamedType) {
            $displayType = $type->getName();
        } else {
            $displayType = (string) $type;
        };

        if (PHP_VERSION_ID < 70300 && isset($typeMap[$displayType])) {
            $displayType = $typeMap[$displayType];
        }

        $displayType = ltrim($displayType, '\\');

        if ($type->allowsNull()) {
            $displayType .= ' or NULL';
        }

        return $displayType;
    }
}
