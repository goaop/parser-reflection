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

namespace Go\ParserReflection;

use Go\ParserReflection\Resolver\NodeExpressionResolver;
use PhpParser\Node;
use PhpParser\Node\Param;
use PhpParser\Node\PropertyItem;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassConst;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\Property;
use ReflectionAttribute as BaseReflectionAttribute;

/**
 * ref original usage https://3v4l.org/duaQI
 *
 * @extends \ReflectionAttribute<object>
 */
class ReflectionAttribute extends BaseReflectionAttribute
{
    /**
     * Fully-qualified attribute class name.
     *
     * @var class-string<object>
     */
    private string $attributeName;

    /**
     * @param class-string<object> $attributeName
     * @param array<int, mixed> $arguments
     */
    public function __construct(
        string $attributeName,
        private ReflectionClass|ReflectionEnum|ReflectionMethod|ReflectionProperty|ReflectionClassConstant|ReflectionFunction|ReflectionParameter|ReflectionEnumUnitCase|ReflectionEnumBackedCase $reflector,
        private array $arguments,
        private bool $isRepeated,
    ) {
        $this->attributeName = $attributeName;
    }

    public function getNode(): Node\Attribute
    {
        $reflectorNode = $this->reflector->getNode();

        // attrGroups only exists in Property Stmt (not PropertyItem), so switch to the type node
        if ($reflectorNode instanceof PropertyItem && $this->reflector instanceof ReflectionProperty) {
            $node = $this->reflector->getTypeNode();
        } else {
            $node = $reflectorNode;
        }

        if ($node instanceof PropertyItem) {
            throw new ReflectionException('ReflectionAttribute cannot resolve attrGroups from a PropertyItem node');
        }

        $nodeExpressionResolver = new NodeExpressionResolver($this);
        foreach ($node->attrGroups as $attrGroup) {
            foreach ($attrGroup->attrs as $attr) {
                $attributeNodeName = $attr->name;
                // Unpack fully-resolved class name from attribute if we have it
                if ($attributeNodeName->hasAttribute('resolvedName')) {
                    $resolvedName = $attributeNodeName->getAttribute('resolvedName');
                    if ($resolvedName instanceof \PhpParser\Node\Name) {
                        $attributeNodeName = $resolvedName;
                    }
                }
                if ($attributeNodeName->toString() !== $this->attributeName) {
                    continue;
                }

                $arguments = [];
                foreach ($attr->args as $arg) {
                    $nodeExpressionResolver->process($arg->value);
                    $arguments[] = $nodeExpressionResolver->getValue();
                }

                if ($arguments !== $this->arguments) {
                    continue;
                }

                return $attr;
            }
        }

        throw new ReflectionException('ReflectionAttribute should be initiated from Go\ParserReflection Reflection classes');
    }

    public function isRepeated(): bool
    {
        return $this->isRepeated;
    }

    /**
     * {@inheritDoc}
     *
     * @return array<int, mixed>
     */
    public function getArguments(): array
    {
        return $this->arguments;
    }

    /**
     * {@inheritDoc}
     */
    public function getName(): string
    {
        return $this->attributeName;
    }

    /**
     * {@inheritDoc}
     */
    public function getTarget(): int
    {
        throw new \RuntimeException(sprintf('cannot get target from %s', $this::class));
    }

    /**
     * {@inheritDoc}
     */
    public function newInstance(): object
    {
        throw new \RuntimeException(sprintf('cannot create new instance from %s', $this::class));
    }
}
