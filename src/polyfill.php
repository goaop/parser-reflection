<?php
/**
 * Parser Reflection API
 *
 * @copyright Copyright 2015, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * This file is for ployfilling classes not defined in all supported
 * versions of PHP, (i.e. PHP < 7).
 */
if (!class_exists(ReflectionType::class, false)) {
    class ReflectionType {
        public function allowsNull()
        {
            return TRUE;
        }
    }
}
