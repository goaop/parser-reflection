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
use Go\ParserReflection\Resolver\NodeExpressionResolver;
use PhpParser\Node\Name;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\ClassConst;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\EnumCase;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\Property;

trait AttributeResolverTrait
{
    /**
     * Returns the AST node that contains attribute groups for this reflection element.
     */
    abstract protected function getNodeForAttributes(): ClassLike|ClassMethod|Function_|Param|ClassConst|EnumCase|Property;

    /**
     * @param class-string<object>|null $name
     * @return ReflectionAttribute[]
     */
    public function getAttributes(?string $name = null, int $flags = 0): array
    {
        $node = $this->getNodeForAttributes();

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
     * Normalizes an attribute class name from a Name node, without triggering autoloading
     * or registering any class aliases, to keep reflection side-effect free.
     *
     * @param mixed $nameNode
     * @return class-string<object>
     */
    private static function resolveAttributeClassName(mixed $nameNode): string
    {
        $className = $nameNode instanceof Name
            ? $nameNode->toString()
            : (is_scalar($nameNode) ? (string) $nameNode : '');

        $className = ltrim($className, '\\');

        if ($className === '') {
            throw new \LogicException('Unable to resolve attribute class name from node');
        }

        return $className;
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
