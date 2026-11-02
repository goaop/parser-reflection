<?php
/**
 * Parser Reflection API
 *
 * @copyright Copyright 2026, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */
declare(strict_types=1);

namespace Go\ParserReflection\Stub\MissingAncestors;

use Go\ParserReflection\Stub\MissingAncestors\Generated\MissingInterface;
use Go\ParserReflection\Stub\MissingAncestors\Generated\MissingParent;
use Go\ParserReflection\Stub\MissingAncestors\Generated\MissingTrait;

/*
 * The ancestors from the Generated namespace are never declared and can not be located: this file is only parsed,
 * it must never be included
 */

interface AvailableInterface
{
    public const INTERFACE_CONSTANT = 'interface';

    public function availableInterfaceMethod(): void;
}

trait AvailableTrait
{
    public function availableTraitMethod(): void
    {
    }
}

#[Marker]
class ClassWithMissingAncestors extends MissingParent implements AvailableInterface, MissingInterface
{
    use AvailableTrait, MissingTrait;

    public const OWN_CONSTANT = 42;

    public int $ownProperty = 1;

    public function ownMethod(): void
    {
    }

    public function availableInterfaceMethod(): void
    {
    }
}

class ChildOfClassWithMissingAncestors extends ClassWithMissingAncestors
{
    public function childMethod(): void
    {
    }
}

interface InterfaceWithMissingParent extends AvailableInterface, MissingInterface
{
}

class ClassWithAvailableAncestors extends \ArrayObject implements AvailableInterface
{
    use AvailableTrait;

    public function availableInterfaceMethod(): void
    {
    }
}
