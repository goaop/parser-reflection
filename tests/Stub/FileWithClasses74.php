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

// PHP 7.4+ code https://www.php.net/manual/en/migration74.php

/**
 * @see https://php.watch/versions/7.4/typed-properties
 */
class ClassWithPhp74TypedProperties
{
    public int $uninitializedIntProperty;
    public string $uninitializedStringProperty;
    public bool $uninitializedBoolProperty;
    public float $uninitializedFloatProperty;
    public array $uninitializedArrayProperty;
    public object $uninitializedObjectProperty;
    public \DateTime $uninitializedClassProperty;
    public self $uninitializedSelfProperty;

    public int $initializedIntProperty = 10;
    public string $initializedStringProperty = 'foo';
    public bool $initializedBoolProperty = true;
    public float $initializedFloatProperty = 42.0;
    public array $initializedArrayProperty = [10, 20];
    public ?object $initializedNullableObjectProperty = null;

    public static int $initializedStaticIntProperty = 10;
    public static string $initializedStaticStringProperty = 'foo';
    public static bool $initializedStaticBoolProperty = true;
    public static float $initializedStaticFloatProperty = 42.0;
    public static array $initializedStaticArrayProperty = [10, 20];
    public static ?object $initializedStaticNullableObjectProperty = null;
}
