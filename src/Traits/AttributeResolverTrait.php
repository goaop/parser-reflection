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
use Go\ParserReflection\ReflectionException;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassConst;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\Property;

trait AttributeResolverTrait
{
    /**
     * @return ReflectionAttribute[]
     */
    public function getAttributes(?string $name = null, int $flags = 0): array
    {
        $attributes = [];

        /** @var Class_|ClassMethod|Property|ClassConst|Function_|Param $node  */
        $node = $this->getNode();

        foreach ($node->attrGroups as $attrGroup) {
            foreach ($attrGroup->attrs as $attr) {
                $attributeName = $name === null
                    ? $attr->name->toString()
                    : $name;

                $attributes[] = new ReflectionAttribute($attributeName, $this, $flags, $attr->args, $this->resolveTarget($attributeName, $flags));
            }
        }

        return $attributes;
    }

    private function resolveTarget(string $name, int $flags): int
    {
        $originalAttributes = parent::getAttributes($name, $flags);

        foreach ($originalAttributes as $originalAttribute) {
            if ($originalAttribute->getName() === $name) {
                return $originalAttribute->getTarget();
            }
        }

        throw new ReflectionException(sprintf('target not found on attribute %s', $name));
    }
}
