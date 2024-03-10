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

/**
 * @see https://php.watch/versions/8.1/readonly
 */
class ClassWithPhp81ReadOnlyProperties
{
    public readonly int $publicReadonlyInt;

    protected readonly array $protectedReadonlyArray;

    private readonly object $privateReadonlyObject;
}

/**
 * @see https://php.watch/versions/8.1/enums
 */
enum Suit {
    case Clubs;
    case Diamonds;
    case Hearts;
    case Spades;
}

/**
 * @see https://php.watch/versions/8.1/enums#enums-backed
 */
enum HTTPMethods: string
{
    case GET = 'get';
    case POST = 'post';
}

/**
 * @see https://php.watch/versions/8.1/enums#enum-methods
 */
enum HTTPStatus: int
{
    case OK = 200;
    case ACCESS_DENIED = 403;
    case NOT_FOUND = 404;

    public function label(): string {
        return static::getLabel($this);
    }

    public static function getLabel(self $value): string {
        return match ($value) {
            HTTPStatus::OK => 'OK',
            HTTPStatus::ACCESS_DENIED => 'Access Denied',
            HTTPStatus::NOT_FOUND => 'Page Not Found',
        };
    }
}

