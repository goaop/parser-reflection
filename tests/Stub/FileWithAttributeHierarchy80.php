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

namespace Go\ParserReflection\Stub\AttributeHierarchy;

use Attribute;

interface MarkerInterface
{
}

interface ExtendedMarkerInterface extends MarkerInterface
{
}

#[Attribute(Attribute::TARGET_ALL)]
class BaseAttribute
{
    public function __construct(public readonly string $title = 'base')
    {
    }
}

#[Attribute(Attribute::TARGET_ALL)]
class ChildAttribute extends BaseAttribute
{
}

#[Attribute(Attribute::TARGET_ALL)]
class GrandChildAttribute extends ChildAttribute
{
}

#[Attribute(Attribute::TARGET_ALL)]
class InterfaceAttribute implements ExtendedMarkerInterface
{
}

#[Attribute(Attribute::TARGET_ALL)]
class UnrelatedAttribute
{
}

#[ChildAttribute('class-level')]
#[UnrelatedAttribute]
class HookedClass
{
    #[GrandChildAttribute('property-level')]
    public int $hookedProperty = 1;

    #[InterfaceAttribute]
    #[UnrelatedAttribute]
    public function hookedMethod(): void
    {
    }
}
