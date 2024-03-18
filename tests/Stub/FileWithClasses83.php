<?php
/**
 * Parser Reflection API
 *
 * @copyright Copyright 2024, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */
declare(strict_types=1);

namespace Go\ParserReflection\Stub;

// PHP 8.3+ code https://www.php.net/manual/en/migration83.php

define('PHP83_OBJECT_CONSTANT', new \Exception('PHP83'));

/**
 * @see https://php.watch/versions/8.3/typed-constants
 */
class ClassWithPhp83TypedConstants
{
    public const int INT_CONSTANT = 10;
    public const string STRING_CONSTANT = 'foo';
    public const bool BOOL_CONSTANT = true;
    public const float FLOAT_CONSTANT = 42.0;
    public const array ARRAY_CONSTANT = [10, 20];
    public const object OBJECT_CONSTANT = PHP83_OBJECT_CONSTANT;
    public const \Throwable&\Stringable INTERSECTED_OBJECT_CONSTANT = PHP83_OBJECT_CONSTANT;
    public const string|int UNION_OBJECT_CONSTANT = 'test';
    final public const string FINAL_STRING_CONSTANT = 'final';
}

