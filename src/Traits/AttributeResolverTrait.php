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
use Go\ParserReflection\ReflectionClass;
use Go\ParserReflection\ReflectionException;
use Go\ParserReflection\ReflectionFunction;
use Go\ParserReflection\ReflectionMethod;
use Go\ParserReflection\ReflectionProperty;
use ReflectionClassConstant;

trait AttributeResolverTrait
{
    /**
     * @return ReflectionAttribute[]
     */
    public function getAttributes(?string $name = null, int $flags = 0): array
    {
        if (
            $this instanceof ReflectionClass ||
            $this instanceof ReflectionFunction ||
            $this instanceof ReflectionMethod ||
            $this instanceof ReflectionProperty ||
            $this instanceof ReflectionClassConstant
        ) {
            $attributes = $this->getAttributes($name, $flags);
            $reflectionAttributes = [];
            foreach ($attributes as $attribute) {
                $reflectionAttributes[] = new ReflectionAttribute($attribute->getName());
            }

            return $reflectionAttributes;
        }

        throw new ReflectionException('not yet supported');
    }
}
