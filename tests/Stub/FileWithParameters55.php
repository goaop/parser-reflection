<?php

namespace {
    function testResolveDefaults($a = null, $b = false, $c = true)
    {
    }

    class TestParametersForRootNsClass
    {
        public function foo($a = null, $b = false, $c = true)
        {
        }
    }
}

namespace Go\ParserReflection\Stub {

    use Go\ParserReflection\ReflectionParameter;

    const TEST_PARAMETER = 42;

    function noParameters()
    {
    }

    function singleParameter($test)
    {
    }

    function miscParameters(
        array $arrayParam,
        callable $callableParam,
        \stdClass $objectParam,
        ReflectionParameter $typehintedParamWithNs,
        &$byReferenceParam,
        \Traversable $traversable,
        array $arrayParamWithDefault = array(1, 2, 3),
        array $arrayNullable = null,
        callable $callableNullable = null,
        \stdClass $objectNullable = null,
        &$byReferenceNullable = __FUNCTION__,
        $constParam = TEST_PARAMETER,
        $constValueParam = __NAMESPACE__, // This line is long and should be truncated
    ) {
    }

    class Foo
    {
        public const CLASS_CONST = __CLASS__;

        public function methodParam($firstParam, $optionalParam = null)
        {
        }

        public function methodParamConst($firstParam = self::CLASS_CONST, $another = __CLASS__, $ns = TEST_PARAMETER, $someOther = SubFoo::ANOTHER_CLASS_CONST)
        {
        }

        public function methodParamBuiltInClassConst($firstParam = \DateTime::ATOM)
        {
        }
    }

    class SubFoo extends Foo
    {
        public const ANOTHER_CLASS_CONST = __CLASS__;

        public function anotherMethodParam(self $selfParam, parent $parentParam)
        {
        }
    }
}
