<?php
/**
 * Parser Reflection API
 *
 * @copyright Copyright 2022, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */
declare(strict_types=1);

namespace Go\ParserReflection;

use Go\ParserReflection\Traits\InternalPropertiesEmulationTrait;
use PhpParser\Node\Attribute;
use ReflectionAttribute as BaseReflectionAttribute;

/**
 * ReflectionAttribute is a class that represents an attribute.
 *
 * @template T of object
 */
class ReflectionAttribute extends BaseReflectionAttribute
{
    use InternalPropertiesEmulationTrait;

    /**
     * Initializes reflection instance for given AST-node
     *
     * @param string    $attributeName
     * @param Attribute $attribute
     *
     * @noinspection PhpMissingParentConstructorInspection
     */
    public function __construct(
        private string $attributeName,
        private Attribute $attribute,
    ) {}

    /**
     * Emulating original behaviour of reflection.
     *
     * Called when invoking {@link var_dump()} on an object
     *
     * @return array
     */
    public function __debugInfo(): array
    {
        return [];
    }

    /**
     * Returns textual representation of the attribute
     *
     * @return string
     */
    public function __toString(): string
    {
        return '';
    }
}