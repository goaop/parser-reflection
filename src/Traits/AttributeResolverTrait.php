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
use PhpParser\Node\Name;

trait AttributeResolverTrait
{
    /**
     * @param class-string<object>|null $name
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
                $resolvedAttrName = self::resolveAttributeClassName($attributeNameNode);
                if ($name === null) {
                    $attributes[] = new ReflectionAttribute($resolvedAttrName, $this, $arguments, $this->isAttributeRepeated($resolvedAttrName, $node->attrGroups));

                    continue;
                }

                if ($name !== $resolvedAttrName) {
                    continue;
                }

                $attributes[] = new ReflectionAttribute($name, $this, $arguments, $this->isAttributeRepeated($name, $node->attrGroups));
            }
        }

        return $attributes;
    }

    /**
     * Resolves the attribute class name from a Name node, returning it as a class-string.
     *
     * Attribute names in PHP are always class names. This method attempts to load the class
     * via autoloading so PHPStan can narrow the type. For classes that cannot be autoloaded
     * (e.g., optional dependency attributes), a cache entry is used.
     *
     * @param mixed $nameNode
     * @return class-string<object>
     */
    private static function resolveAttributeClassName(mixed $nameNode): string
    {
        $className = $nameNode instanceof Name ? $nameNode->toString() : (is_scalar($nameNode) ? (string) $nameNode : '');
        $className = ltrim($className, '\\');
        // Fast path: already loaded without autoloading
        if (class_exists($className, false) || interface_exists($className, false) || trait_exists($className, false) || enum_exists($className, false)) {
            return $className;
        }
        // Try with autoloading
        if (class_exists($className) || interface_exists($className) || trait_exists($className) || enum_exists($className)) {
            return $className;
        }
        // For optional/not-installed attribute classes (e.g. JetBrains PhpStorm attributes),
        // register as stdClass alias so the type is narrowable by PHPStan via class_exists()
        class_alias(\stdClass::class, $className);
        $registeredName = $className;
        if (class_exists($registeredName, false)) {
            return $registeredName;
        }
        throw new \LogicException("class_alias failed unexpectedly for attribute class: $className");
    }

    /**
     * @param \PhpParser\Node\AttributeGroup[] $attrGroups
     */
    private function isAttributeRepeated(string $attributeName, array $attrGroups): bool
    {
        $count = 0;

        foreach ($attrGroups as $attrGroup) {
            foreach ($attrGroup->attrs as $attr) {
                $attributeNameNode = $attr->name;
                // If we have resoled node name, then we should use it instead
                if ($attributeNameNode->hasAttribute('resolvedName')) {
                    $resolvedNameNode = $attributeNameNode->getAttribute('resolvedName');
                    if ($resolvedNameNode instanceof Name) {
                        $attributeNameNode = $resolvedNameNode;
                    }
                }

                if ($attributeNameNode->toString() === $attributeName) {
                    ++$count;
                }
            }
        }

        return $count >= 2;
    }
}
