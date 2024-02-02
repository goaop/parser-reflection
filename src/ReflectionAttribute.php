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
        private int $flags = 0,
        private Class_|ClassMethod|Function_|ClassConst|Property|Param|null $attributeHolder = null
    ) {
    }

    public function getNode(): Node\Attribute
    {
        foreach ($this->attributeHolder->attrGroups as $attrGroup) {
            foreach ($attrGroup->attrs as $attr) {
                if ($attr->name->toString() === $this->attributeName) {
                    return $attr;
                }
            }
        }

        throw new ReflectionException(sprintf('attribute %s not exists', $this->attributeName));
    }
}
