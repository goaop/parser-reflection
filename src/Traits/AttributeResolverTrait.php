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
use Go\ParserReflection\ReflectionClass as ParsedReflectionClass;
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
     * Default implementation delegates to getNode(). Override when the attribute-bearing
     * node differs from getNode() (e.g. ReflectionProperty).
     */
    protected function getNodeForAttributes(): ClassLike|ClassMethod|Function_|Param|ClassConst|EnumCase|Property
    {
        /** @phpstan-ignore return.type */
        return $this->getNode(); // @phpstan-ignore-line
    }

    /**
     * @param class-string<object>|null $name
     * @return ReflectionAttribute[]
     */
    public function getAttributes(?string $name = null, int $flags = 0): array
    {
        if ($flags !== 0 && $flags !== \ReflectionAttribute::IS_INSTANCEOF) {
            throw new \ValueError(
                $this->getAttributeFilterOwnerName()
                . '::getAttributes(): Argument #2 ($flags) must be a valid attribute filter flag'
            );
        }

        $node = $this->getNodeForAttributes();

        $filterByInstanceOf     = $name !== null && ($flags & \ReflectionAttribute::IS_INSTANCEOF) !== 0;
        $attributes             = [];
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

                if ($filterByInstanceOf) {
                    if (!self::isAttributeInstanceOf($resolvedAttrName, $name)) {
                        continue;
                    }

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
     * Returns the name of the internal reflection class that declares getAttributes(), used to build
     * the same \ValueError message as the engine does for an invalid filter flag.
     */
    private function getAttributeFilterOwnerName(): string
    {
        return match (true) {
            $this instanceof \ReflectionFunctionAbstract => 'ReflectionFunctionAbstract',
            $this instanceof \ReflectionParameter        => 'ReflectionParameter',
            $this instanceof \ReflectionProperty         => 'ReflectionProperty',
            $this instanceof \ReflectionClassConstant    => 'ReflectionClassConstant',
            default                                      => 'ReflectionClass',
        };
    }

    /**
     * Checks that an attribute class is the given class, extends it or implements it, following the
     * instanceof semantics of \ReflectionAttribute::IS_INSTANCEOF without triggering autoloading.
     */
    private static function isAttributeInstanceOf(string $attributeClassName, string $filterClassName): bool
    {
        $filterClassName = strtolower(ltrim($filterClassName, '\\'));
        if ($filterClassName === '') {
            return false;
        }

        $classNamesToVisit = [$attributeClassName];
        $visitedClassNames = [];

        while ($classNamesToVisit !== []) {
            $currentClassName = ltrim(array_shift($classNamesToVisit), '\\');
            $lowerClassName   = strtolower($currentClassName);
            if ($lowerClassName === '' || isset($visitedClassNames[$lowerClassName])) {
                continue;
            }
            $visitedClassNames[$lowerClassName] = true;

            if ($lowerClassName === $filterClassName) {
                return true;
            }

            foreach (self::resolveClassAncestorNames($currentClassName) as $ancestorClassName) {
                $classNamesToVisit[] = $ancestorClassName;
            }
        }

        return false;
    }

    /**
     * Resolves direct and inherited ancestors of a class without loading it: already loaded classes are
     * inspected with native reflection, everything else is resolved from the AST via the current locator.
     *
     * @return list<string>
     */
    private static function resolveClassAncestorNames(string $className): array
    {
        if (class_exists($className, false) || interface_exists($className, false)) {
            $reflection = new \ReflectionClass($className);
        } else {
            try {
                $reflection = new ParsedReflectionClass($className);
            } catch (\Throwable) {
                // Attribute classes are not required to exist until an attribute is instantiated
                return [];
            }
        }

        $ancestorNames = [];
        try {
            $ancestorNames = $reflection->getInterfaceNames();
        } catch (\Throwable) {
            // Unresolvable interfaces simply do not participate in the instanceof check
        }

        try {
            $parentClass = $reflection->getParentClass();
            if ($parentClass !== false) {
                $ancestorNames[] = $parentClass->getName();
            }
        } catch (\Throwable) {
            // Same for an unresolvable parent class
        }

        return $ancestorNames;
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
