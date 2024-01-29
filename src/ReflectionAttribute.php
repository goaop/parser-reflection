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

use Go\ParserReflection\Traits\InternalPropertiesEmulationTrait;
use ReflectionAttribute as BaseReflectionAttribute;
use PhpParser\Node;
use Reflector;

/**
 * ref original usage https://3v4l.org/duaQI
 */
class ReflectionAttribute extends BaseReflectionAttribute
{
    use InternalPropertiesEmulationTrait;

    public function __construct(
        string $attributeName,
        private ?Node\Attribute $attributeNode = null,
        private ?Node\Stmt\ClassLike $classLikeNode = null,
        private ?Reflector $declaringReflector = null
    ) {
        $this->attributeNode ??= ReflectionEngine::parseAttribute($attributeName);
        $this->classLikeNode ??= ReflectionEngine::parseClass($attributeName);
    }

    public function __debugInfo(): array
    {
        return [];
    }

    public function getNode(): Node\Attribute
    {
        return $this->attributeNode;
    }
}
