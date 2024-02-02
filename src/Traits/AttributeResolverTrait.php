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

namespace Go\ParserReflection\Traits;

use Go\ParserReflection\ReflectionAttribute;

trait AttributeResolverTrait
{
    /**
     * @return ReflectionAttribute[]
     */
    public function getAttributes(?string $name = null, int $flags = 0): array
    {
        $attributes = [];

        $originalAttributes = parent::getAttributes($name, $flags);
        foreach ($originalAttributes as $originalAttribute) {
            $attributeName = $originalAttribute->getName();
            $attributes[] = new ReflectionAttribute($attributeName, $this, $flags, $originalAttribute->getArguments(), $originalAttribute->getTarget());
        }

        return $attributes;
    }
}
