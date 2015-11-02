<?php

namespace ParserReflection\Stub;

abstract class AbstractClassWithMethods
{
    public function __construct(){}
    public function __destruct(){}
    public function explicitPublicFunc(){}
    function implicitPublicFunc(){}
    protected function protectedFunc(){}
    private function privateFunc(){}
    static function staticFunc(){}
    abstract function abstractFunc();
    final function finalFunc(){}

    /**
     * @return string
     */
    public function funcWithDocAndBody()
    {
        static $a =5, $test = '1234';

        return 'hello';
    }
}