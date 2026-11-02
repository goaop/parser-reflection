<?php

declare(strict_types=1);
/**
 * Parser Reflection API
 *
 * @copyright Copyright 2026, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\ParserReflection;

/**
 * Tells what the reflection of a class does when an ancestor (parent class, interface or trait) can not be located
 *
 * An ancestor may be missing when it is generated at runtime and loaded by another autoloader, declared by
 * class_alias(), or defined outside the files known to the locator.
 *
 * @see ReflectionEngine::init()
 * @see ReflectionEngine::setMissingAncestorPolicy()
 */
enum MissingAncestorPolicy
{
    /**
     * Every method that needs a missing ancestor throws a {@see ClassNotFoundException}
     *
     * This is the default policy.
     */
    case Strict;

    /**
     * Missing ancestors are left out: the reflection covers what the class and its available ancestors declare
     *
     * Such a reflection reports itself as partial, see {@see ReflectionClass::isPartial()} and
     * {@see ReflectionClass::getMissingClassNames()}.
     */
    case Partial;
}
