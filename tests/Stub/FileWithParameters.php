<?php

namespace Go\ParserReflection\Stub;

use Go\ParserReflection\ReflectionParameter;

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
    $constValueParam = __NAMESPACE__ // This line is long and should be truncated
) {}

class Foo {
    const CLASS_CONST = __CLASS__;

    public function methodParam($firstParam, $optionalParam = null) {}
    public function methodParamConst($firstParam = self::CLASS_CONST, $another = __CLASS__, $ns = TEST_PARAMETER) {}
}
