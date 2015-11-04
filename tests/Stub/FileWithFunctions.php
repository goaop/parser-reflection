<?php

namespace ParserReflection\Stub {
    function simpleNoArgs() {}
    function simpleArg($test) {}
    function generatorFunc() {
        yield 100500;
    }
    function noGeneratorFunc() {
        $a = function () {
            yield 42;
        };

        return 100;
    }
    function funcWithStaticVars() {
        static $a = 42, $b = 'foo';
    }
    function funcWithoutStaticVars() {
        $a = function() {
            static $b = false, $c = 100.0;
        };
        return $a;
    }
}

namespace {

    /**
     * Simple documentation
     */
    function __globalFunc() {}
}