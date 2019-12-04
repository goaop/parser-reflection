<?php
/**
 * Parser Reflection API
 *
 * @copyright Copyright 2015, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

use Go\ParserReflection\Locator\ComposerLocator;
use Go\ParserReflection\ReflectionEngine;

/**
 * This file is used for automatic configuration of
 * Go\ParserReflection\ReflectionEngine class
 */
ReflectionEngine::init(new ComposerLocator());
