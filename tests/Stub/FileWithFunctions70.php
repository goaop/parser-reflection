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

    // These types *LOOK LIKE* built in types, but they aren't.
    function wrongBuiltInArgs(
        integer $a,
        double  $b,
        boolean $c,
        str     $d,
        object  $e,
        closure $f,
        arr     $g
    ) : integer {}

    // These are the *REAL* type hints for the types above.
    function builtInArgs(
        int      $a,
        float    $b,
        bool     $c,
        string   $d,
                 $e, /* there is no generic object typehint */
        callable $f,
        array    $g
    ) : int {}

    // Remember, since integer isn't a valid builtin typehint, it's actually a class name.
    function mixedArgs(\DateTime $a, ReflectionMethod $b, bool $c, Fred\ReflectionEngine $d) : integer {}
}
