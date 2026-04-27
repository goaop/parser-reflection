<?php

declare(strict_types=1);
/**
 * Parser Reflection API
 *
 * @copyright Copyright 2021, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\ParserReflection;

use ReflectionNamedType as BaseReflectionNamedType;

/**
 * ReflectionNamedType implementation
 */
class ReflectionNamedType extends BaseReflectionNamedType
{
    /**
     * Initializes reflection data
     */
    public function __construct(
        private readonly string $type,
        private readonly bool $allowsNull,
        private readonly bool $isBuiltin
    ) {}

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
    public function isBuiltin(): bool
    {
        return $this->isBuiltin;
    }

    /**
     * @inheritDoc
     */
    public function __toString(): string
    {
        $allowsNull = $this->allowsNull && !in_array($this->type,['null', 'mixed'], true);

        return $allowsNull ? '?' . $this->type : $this->type;
    }

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return $this->type;
    }
}
