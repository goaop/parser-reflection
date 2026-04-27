<?php
/**
 * Parser Reflection API
 *
 * @copyright Copyright 2015, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\ParserReflection\Stub;

function arrayVariadicParameters(array ...$acceptsArrays) {}
function callableVariadicParameters(callable ...$acceptsArrays) {}
function constantExpressionAsDefault($value = 0.5 * 2 * 10, $another = __FUNCTION__ . 'test', $anotherOne = ['test'], $anotherTwo = array('yoo')) {}
function constantExponentiation($value = 0.5 * 2**2) {}
