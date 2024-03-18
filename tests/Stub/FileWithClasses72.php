<?php
/**
 * Parser Reflection API
 *
 * @copyright Copyright 2016, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */
declare(strict_types=1);

namespace Go\ParserReflection\Stub;

use Go\ParserReflection\{ReflectionMethod, ReflectionProperty as P};

// PHP 7.2+ code https://www.php.net/manual/en/migration72.php

/**
 * @see https://wiki.php.net/rfc/object-typehint
 */
class ClassWithPhp72ObjectTypeHints
{
    public function acceptsObject(object $object) {}
    public function acceptsNullableObject(?object $object) {}
    public function returnsObject(): object {}
    public function returnsNullableObject(): ?object {}
}

/**
 * @see https://www.php.net/manual/en/migration72.new-features.php#migration72.new-features.param-type-widening
 * @see https://wiki.php.net/rfc/parameter-no-type-variance
 */
class ClassWithPhp72ParameterTypeWidening extends ClassWithPhp72ObjectTypeHints
{
    public function acceptsObject($object) {}
}

/**
 * @see https://wiki.php.net/rfc/parameter-no-type-variance
 * @see https://www.php.net/manual/en/language.oop5.variance.php
 */
class ClassWithPhp72ReturnTypeNarrowing extends ClassWithPhp72ObjectTypeHints
{
    public function returnsObject(): \Traversable {}
}