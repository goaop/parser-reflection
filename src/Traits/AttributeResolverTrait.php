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
use Go\ParserReflection\ValueResolver\NodeExpressionResolver;

trait AttributeResolverTrait
{
    /**
     * @return ReflectionAttribute[]
     */
    public function getAttributes(?string $name = null, int $flags = 0): array
    {
        $node = $this->getNode();
        $attributes = [];
        $nodeExpressionResolver = new NodeExpressionResolver($this);

        foreach ($node->attrGroups as $attrGroup) {
            foreach ($attrGroup->attrs as $attr) {
                $arguments = [];
                foreach ($attr->args as $arg) {
                    $nodeExpressionResolver->process($arg->value);
                    $arguments[] = $nodeExpressionResolver->getValue();
                }

                if ($name === null) {
                    $attributes[] = new ReflectionAttribute($attr->name->toString(), $this, $arguments, $this->isAttributeRepeated($attr->name->toString(), $node->attrGroups));

                    continue;
                }

                if ($name !== $attr->name->toString()) {
                    continue;
                }

                $attributes[] = new ReflectionAttribute($name, $this, $arguments, $this->isAttributeRepeated($name, $node->attrGroups));
            }
        }

        return $attributes;
    }

    private function isAttributeRepeated(string $attributeName, array $attrGroups): bool
    {
        $count = 0;

        foreach ($attrGroups as $attrGroup) {
            foreach ($attrGroup->attrs as $attr) {
                if ($attr->name->toString() === $attributeName) {
                    ++$count;
                }
            }
        }

        return $count >= 2;
    }
}
