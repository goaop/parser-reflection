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
 */
class ReflectionAttribute extends BaseReflectionAttribute
{
    public function __construct(
        private string $attributeName,
        private ReflectionClass|ReflectionMethod|ReflectionProperty|ReflectionClassConstant|ReflectionFunction|ReflectionParameter $reflector,
        private array $arguments,
        private bool $isRepeated,
    ) {
    }

    public function getNode(): Node\Attribute
    {
        /** @var Class_|ClassMethod|PropertyItem|ClassConst|Function_|Param $node  */
        $node = $this->reflector->getNode();

        // attrGroups only exists in Property Stmt
        if ($node instanceof PropertyItem) {
            $node = $this->reflector->getTypeNode();
        }

        $nodeExpressionResolver = new NodeExpressionResolver($this);
        foreach ($node->attrGroups as $attrGroup) {
            foreach ($attrGroup->attrs as $attr) {
                $attributeNodeName = $attr->name;
                // Unpack fully-resolved class name from attribute if we have it
                if ($attributeNodeName->hasAttribute('resolvedName')) {
                    $attributeNodeName = $attributeNodeName->getAttribute('resolvedName');
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
