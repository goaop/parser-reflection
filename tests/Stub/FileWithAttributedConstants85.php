<?php
/**
 * Parser Reflection API
 *
 * @copyright Copyright 2026, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 *
 * Attributes on constants are allowed since PHP 8.5, therefore this file can only be parsed,
 * it must never be included or required by the tests.
 */
declare(strict_types=1);

namespace Go\ParserReflection\Stub {

    use Deprecated as LegacyMarker;

    /**
     * Attribute that can be applied to the constants only
     */
    #[\Attribute(\Attribute::TARGET_CONSTANT)]
    final class ConstantMarker
    {
        public function __construct(public readonly string $tag = '')
        {
        }
    }

    #[\Deprecated(message: 'Use ATTRIBUTED_CONSTANT_MODERN instead', since: '8.5')]
    const ATTRIBUTED_CONSTANT_LEGACY = 'legacy';

    #[LegacyMarker]
    const ATTRIBUTED_CONSTANT_ALIASED = 'aliased';

    #[ConstantMarker(tag: 'marked')]
    const ATTRIBUTED_CONSTANT_MARKED = 'marked';

    const ATTRIBUTED_CONSTANT_MODERN = 'modern';
}

namespace {

    #[\Deprecated]
    const ATTRIBUTED_GLOBAL_CONSTANT = 'global';
}
