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

use Deprecated as ImportedDeprecated;

/**
 * @see https://wiki.php.net/rfc/deprecated_attribute
 */

#[\Deprecated]
function php84DeprecatedFunction(): void
{
}

#[\Deprecated(message: 'use php84PlainFunction() instead', since: '4.0')]
function php84DeprecatedFunctionWithArguments(): void
{
}

#[ImportedDeprecated]
function php84ImportedDeprecatedFunction(): void
{
}

function php84PlainFunction(): void
{
}

class ClassWithPhp84DeprecatedFeatures
{
    #[\Deprecated]
    public const DEPRECATED_CONSTANT = 'deprecated';

    #[\Deprecated(message: 'use ACTUAL_CONSTANT instead', since: '4.0')]
    protected const DEPRECATED_CONSTANT_WITH_ARGUMENTS = 'deprecated';

    #[ImportedDeprecated]
    public const IMPORTED_DEPRECATED_CONSTANT = 'deprecated';

    public const ACTUAL_CONSTANT = 'actual';

    #[\Deprecated]
    public function deprecatedMethod(): void
    {
    }

    #[\Deprecated(message: 'use actualMethod() instead', since: '4.0')]
    public static function deprecatedStaticMethod(): void
    {
    }

    #[ImportedDeprecated]
    public function importedDeprecatedMethod(): void
    {
    }

    public function actualMethod(): void
    {
    }
}
