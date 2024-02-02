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

use ReflectionAttribute as BaseReflectionAttribute;
use PhpParser\Node;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassConst;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\Stmt\PropertyProperty;

/**
 * ref original usage https://3v4l.org/duaQI
 */
class ReflectionAttribute extends BaseReflectionAttribute
{
    private array $arguments;
    private int $target;
    private bool $isRepeated;

    public function __construct(
        private string $attributeName,
        private ReflectionClass|ReflectionMethod|ReflectionProperty|ReflectionClassConstant|ReflectionFunction|ReflectionParameter $reflector,
        \ReflectionAttribute $reflectionAttribute
    ) {
        $this->arguments = $reflectionAttribute->getArguments();
        $this->target = $reflectionAttribute->getTarget();
        $this->isRepeated = $reflectionAttribute->isRepeated();
    }

    public function getNode(): Node\Attribute
    {
        /** @var Class_|ClassMethod|Property|ClassConst|Function_|Param $node  */
        $node = $this->reflector->getNode();

        // attrGroups only exists in Property Stmt
        if ($node instanceof PropertyProperty) {
            $node = $node->getAttribute('parent');
            assert($node instanceof Property);
        }

        foreach ($node->attrGroups as $attrGroup) {
            foreach ($attrGroup->attrs as $attr) {
                if ($attr->name->toString() === $this->attributeName) {
                    return $attr;
                }
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
        return $this->target;
    }
}
