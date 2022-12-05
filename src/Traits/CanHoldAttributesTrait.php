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

namespace Go\ParserReflection\Traits;

use Go\ParserReflection\ReflectionAttribute;
use Go\ParserReflection\ReflectionClass;
use Go\ParserReflection\ReflectionClassConstant;
use Go\ParserReflection\ReflectionEngine;
use Go\ParserReflection\ReflectionException;
use Go\ParserReflection\ReflectionFunction;
use Go\ParserReflection\ReflectionMethod;
use Go\ParserReflection\ReflectionParameter;
use Go\ParserReflection\ReflectionProperty;
use LogicException;

/**
 * Trait for reflection classes that can have attributes
 */
trait CanHoldAttributesTrait
{
    /**
     * Array of attributes
     *
     * @var ReflectionAttribute[]|null
     */
    protected ?array $attributes = null;

    /**
     * Collect attributes from the node
     *
     * @return ReflectionAttribute[]
     */
    private function collectAttributes(): array
    {
        $this->attributes = [];

        $node = match(true) {
            $this instanceof ReflectionClass,
            $this instanceof ReflectionFunction,
            $this instanceof ReflectionMethod,
            $this instanceof ReflectionParameter => $this->getNode(),
            $this instanceof ReflectionClassConstant => $this->getClassConstantNode(),
            $this instanceof ReflectionProperty => $this->isPromoted()
                ? $this->getNode()
                : $this->getTypeNode(),
            default => throw new LogicException('Unsupported reflection type: ' . get_class($this)),
        };

        foreach ($node->attrGroups as $attributeGroup) {
            foreach ($attributeGroup->attrs as $attributeNode) {
                $attributeName = $attributeNode->name->toString();

                try {
                $this->attributes[] = new ReflectionAttribute(
                    $attributeName,
                    $attributeNode,
                    ReflectionEngine::parseClass($attributeName),
                    $this,
                );
                } catch (ReflectionException) {
                    // Ignore attributes that cannot be parsed
                }
            }
        }

        return $this->attributes;
    }
}
