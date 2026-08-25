<?php
/**
 * Parser Reflection API
 *
 * @copyright Copyright 2025, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */
declare(strict_types=1);

namespace Go\ParserReflection\Stub;

/**
 * This file contains PHP 8.6 syntax (readonly properties with default values) and can not be
 * loaded by any current runtime: the feature was merged into php-src after the 8.6.0beta1 tag,
 * so even PHP 8.6.0beta1 still fails with "Readonly property X::$y cannot have default value".
 *
 * It is intended to be analyzed statically only, never include or autoload it directly.
 *
 * @see https://wiki.php.net/rfc/readonly_property_defaults
 */

enum ReadonlyDefaultSuit86: string
{
    case Hearts = 'H';
    case Spades = 'S';
}

class ClassWithReadonlyDefaults86
{
    public const string PREFIX = 'migration_';

    public readonly string $name = 'create_books_table';

    protected readonly int $version = 1;

    private readonly float $ratio = 0.5;

    public readonly bool $enabled = true;

    public readonly ?string $nullableWithNull = null;

    public readonly int $constExprSum = 2 + 3 * 4;

    public readonly string $classConstantConcat = self::PREFIX . 'books';

    public readonly ReadonlyDefaultSuit86 $suit = ReadonlyDefaultSuit86::Spades;

    /** @var array<int, string> */
    public readonly array $list = ['a', 'b'];

    public readonly string $noDefault;

    public private(set) readonly string $privateSetWithDefault = 'restricted';
}

readonly class ReadonlyClassWithDefaults86
{
    public string $name = 'readonly_class_default';

    public int $noDefault;
}

trait ReadonlyDefaultsTrait86
{
    public readonly string $fromTrait = 'trait_default';
}

class ParentWithReadonlyDefaults86
{
    public readonly string $inherited = 'parent_default';
}

class ChildWithReadonlyDefaults86 extends ParentWithReadonlyDefaults86
{
    use ReadonlyDefaultsTrait86;

    public readonly int $own = 7;
}

class ClassWithPromotedReadonlyDefaults86
{
    public function __construct(
        public readonly string $promotedReadonly = 'promoted_default',
        protected readonly int $promotedReadonlyInt = 42,
    ) {
    }
}
