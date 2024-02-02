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
use Reflector;

/**
 * ref original usage https://3v4l.org/duaQI
 */
class ReflectionAttribute extends BaseReflectionAttribute
{
    public function __construct(
        private string $attributeName,
        private Reflector $reflector,
        private int $flags = 0
    ) {
    }

    public function getNode(): Node\Attribute
    {
        if (
            ! $this->reflector instanceof ReflectionClass &&
            ! $this->reflector instanceof ReflectionMethod &&
            ! $this->reflector instanceof ReflectionProperty &&
            ! $this->reflector instanceof ReflectionClassConstant &&
            ! $this->reflector instanceof ReflectionFunction &&
            ! $this->reflector instanceof ReflectionParameter) {
            throw new ReflectionException(sprintf('attribute node not available at ', $this->reflector::class));
        }

        /** @var Class_|ClassMethod|Property|ClassConst|Function_|Param $node  */
        $node = $this->reflector->getNode();
        foreach ($node->attrGroups as $attrGroup) {
            foreach ($attrGroup->attrs as $attr) {
                if ($attr->name->toString() === $this->attributeName) {
                    return $attr;
                }
            }
        }

        throw new ReflectionException('ReflectionAttribute should be initiated from Go\ParserReflection Reflection classes');
    }
}
