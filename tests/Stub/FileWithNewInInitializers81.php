<?php
declare(strict_types=1);
/**
 * Parser Reflection API
 *
 * @copyright Copyright 2025, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\ParserReflection\Stub;

/**
 * Stub file with "new in initializers" syntax, available since PHP 8.1.
 *
 * File name intentionally does not follow the PSR-4 name of the classes below, so these classes can not be
 * found by the composer autoloader and have to be resolved via the registered locator instead.
 */

class NewInInitializerDependency
{
    public function __construct(public readonly string $label = 'default')
    {
    }
}

class ClassWithNewInInitializers
{
    public function withDependency(
        NewInInitializerDependency $dependency = new NewInInitializerDependency('injected')
    ): NewInInitializerDependency {
        return $dependency;
    }
}
