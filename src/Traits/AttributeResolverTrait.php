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
use Go\ParserReflection\ReflectionProperty;
use Go\ParserReflection\Resolver\NodeExpressionResolver;

trait AttributeResolverTrait
{
    /**
     * @return ReflectionAttribute[]
     */
    public function getAttributes(?string $name = null, int $flags = 0): array
    {
        if ($this instanceof ReflectionProperty) {
            $node = $this->getTypeNode();
        } else {
            $node = $this->getNode();
        }

        $attributes = [];
        $nodeExpressionResolver = new NodeExpressionResolver($this);

        foreach ($node->attrGroups as $attrGroup) {
            foreach ($attrGroup->attrs as $attr) {
                $arguments = [];
                foreach ($attr->args as $arg) {
                    $nodeExpressionResolver->process($arg->value);
                    $arguments[] = $nodeExpressionResolver->getValue();
                }

                $attributeNameNode = $attr->name;
                // If we have resoled node name, then we should use it instead
                if ($attributeNameNode->hasAttribute('resolvedName')) {
                    $attributeNameNode = $attributeNameNode->getAttribute('resolvedName');
                }
                if ($name === null) {
                    $attributes[] = new ReflectionAttribute($attributeNameNode->toString(), $this, $arguments, $this->isAttributeRepeated($attributeNameNode->toString(), $node->attrGroups));

                    continue;
                }

                if ($name !== $attributeNameNode->toString()) {
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
                $attributeNameNode = $attr->name;
                // If we have resoled node name, then we should use it instead
                if ($attributeNameNode->hasAttribute('resolvedName')) {
                    $attributeNameNode = $attributeNameNode->getAttribute('resolvedName');
                }

                if ($attributeNameNode->toString() === $attributeName) {
                    ++$count;
                }
            }
        }

        return $count >= 2;
    }
}
