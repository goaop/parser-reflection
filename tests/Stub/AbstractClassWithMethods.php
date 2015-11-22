<?php

namespace Go\ParserReflection\Stub;

/**
 * @link https://bugs.php.net/bug.php?id=70957 self::class can not be resolved with reflection for abstract class
 */
abstract class AbstractClassWithMethods
{
    const TEST = 5;

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

    /**
     * @return \Generator
     */
    public function generatorYieldFunc()
    {
        $index = 0;
        while ($index < 1e3) {
            yield $index;
        }
    }

    /**
     * @return int
     */
    public function noGeneratorFunc()
    {
        $gen = function () {
            yield 10;
        };

        return 10;
    }

    private function testParam($a, $b = null, $d = self::TEST) {}
}