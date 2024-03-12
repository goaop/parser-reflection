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

/**
 * @see https://php.watch/versions/8.2/readonly-classes
 */
readonly class ClassWithPhp82ReadOnlyFlag
{
    public int $publicInt;
}

/**
 * @see https://php.watch/versions/8.2/dnf-types
 */
class ClassWithPhp82DNFType
{
    private (JSONResponse&SuccessResponse)|HTMLResponse|string $respond;

    public function __construct((JSONResponse&SuccessResponse)|HTMLResponse|string $respond)
    {
        $this->respond = $respond;
    }

    public function respond(): (JSONResponse&SuccessResponse)|HTMLResponse|string
    {
        return $this->respond;
    }
}

/**
 * @see https://php.watch/versions/8.2/null-false-types
 * @see https://php.watch/versions/8.2/true-type
 */
class ClassWithPhp82NullFalseTypes
{
    private true $isTrue = true;
    private false $isFalse = false;
    private null $isNull = null;

    public function returnsFalse(): false
    {
        return false;
    }

    public function returnsTrue(): true
    {
        return true;
    }

    public function returnsNullExplicitly(): null
    {
        return null;
    }
}
