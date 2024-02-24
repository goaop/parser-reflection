<?php
declare(strict_types=1);

namespace Go\ParserReflection\Stub {

    use Go\ParserReflection\ReflectionMethod;

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

    function builtInArgs(int $a, float $b, bool $c, string $d, object $e) : int {}
}
