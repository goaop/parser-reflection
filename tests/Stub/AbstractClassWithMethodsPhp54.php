<?php

namespace Go\ParserReflection\Stub;

class BaseClass
{
    protected static function prototypeMethod()
    {
        return __CLASS__;
    }
}

/**
 * @link https://bugs.php.net/bug.php?id=70957 self::class can not be resolved with reflection for abstract class
 */
abstract class AbstractClassWithMethodsPhp54 extends BaseClass
{
    const TEST = 5;

    public function __construct(){}
    public function __destruct(){}
    public function explicitPublicFunc(){}
    function implicitPublicFunc(){}
    protected function protectedFunc(){}
    private function privateFunc(){}
    static function staticFunc(){}
    protected static function protectedStaticFunc(){}
    abstract function abstractFunc();
    final function finalFunc(){}

    /**
     * @return string
     */
    public static function funcWithDocAndBody()
    {
        static $a =5, $test = '1234';

        return 'hello';
    }

    public static function funcWithReturnArgs($a, $b = 100, $c = 10.0)
    {
        return [$a, $b, $c];
    }

    public static function prototypeMethod()
    {
        return __CLASS__;
    }

    private function testParam($a, $b = null, $d = self::TEST) {}
}
