<?php

namespace ParserReflection\Stub;

use ParserReflection\ReflectionParameter;

const TEST_PARAMETER = 42;

function noParameters() {}
function singleParameter($test) {}
function miscParameters(
    array $arrayParam,
    array $arrayParamWithDefault = array(1,2,3),
    array $arrayNullable = null,
    callable $callableParam,
    callable $callableNullable = null,
    \stdClass $objectParam,
    \stdClass $objectNullable = null,
    ReflectionParameter $typehintedParamWithNs,
    &$byReferenceParam,
    &$byReferenceNullable = __CLASS__,
    $constParam = TEST_PARAMETER,
    $constValueParam = 42.0
) {}

class Foo {
    public function methodParam($firstParam, $optionalParam = null) {}
}
