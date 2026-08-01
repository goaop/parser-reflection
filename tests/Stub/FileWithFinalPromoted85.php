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
 * This file contains PHP 8.5 syntax and can not be loaded by an older runtime.
 * It is intended to be analyzed statically only, never include it directly.
 *
 * @see https://wiki.php.net/rfc/final_promotion
 */

class ClassWithFinalPromotedProperty85
{
    public function __construct(
        public final int $finalPromoted = 1,
        public int $plainPromoted = 2,
        protected final readonly string $finalReadonlyPromoted = 'value',
        private(set) string $privateSetPromoted = 'implicitly final'
    ) {
    }
}
