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
use Go\ParserReflection\ReflectionClass;
use Go\ParserReflection\ReflectionClassConstant;
use Go\ParserReflection\ReflectionException;
use Go\ParserReflection\ReflectionFunction;
use Go\ParserReflection\ReflectionMethod;
use Go\ParserReflection\ReflectionProperty;
use PhpParser\Node\Stmt\ClassConst;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\Property;

trait AttributeResolverTrait
{
    /**
     * @return ReflectionAttribute[]
     */
    public function getAttributes(?string $name = null, int $flags = 0): array
    {
        /** @var ClassLike|ClassMethod|Property|ClassConst|Function_|null $attributeHolder */
        $attributeHolder = null;

        if ($this instanceof ReflectionClass) {
            $attributeHolder = $this->classLikeNode;
        }

        if ($this instanceof ReflectionMethod) {
            $attributeHolder = $this->classMethodNode;
        }

        if ($this instanceof ReflectionProperty) {
            $attributeHolder = $this->propertyNode;
        }

        if ($this instanceof ReflectionClassConstant) {
            $attributeHolder = $this->classConstNode;
        }

        if ($this instanceof ReflectionFunction) {
            $attributeHolder = $this->functionNode;
        }

        if ($attributeHolder === null) {
            throw new ReflectionException(sprintf('Attribute on %s not supported yet'), $this::class);
        }

        $attributes = [];
        foreach ($attributeHolder->attrGroups as $attrGroup) {
            foreach ($attrGroup->attrs as $attr) {
                if ($name !== null) {
                    $attributes[] = new ReflectionAttribute($name, $flags, $attributeHolder);
                } else {
                    $attributes[] = new ReflectionAttribute($attr->toString(), $flags, $attributeHolder);
                }
            }
        }

        return $attributes;
    }
}
