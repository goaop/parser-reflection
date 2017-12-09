<?php

namespace Go\ParserReflection\Stub {

    use Go\ParserReflection\ReflectionMethod;
    use Go\ParserReflection as Fred;

    function simpleIntArg(int $value) {}
    function simpleArrayOut() : array {}
    function optionalCallableArg(callable $argument = null) : callable {}
    function objectOut() : \Exception
    {
        return new \Exception();
    }
    function relativeObjectOut() : ReflectionMethod
    {
    }

    function builtInArgs(int $a, float $b, bool $c, string $d, object $e) : integer {}

    function mixedArgs(\DateTime $a, ReflectionMethod $b, bool $c, Fred\ReflectionEngine $d) : integer {}
}
