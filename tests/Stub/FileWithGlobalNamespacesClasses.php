<?php
require_once __DIR__ . '/FileWithClasses55.php';

use Go\ParserReflection\Stub\ClassWithScalarConstants;

class GlobalNsClass
{
    const A = 10;
}

class AnotherGlobalNsClass
{
    public function slashedGlobalNs($param = \GlobalNsClass::A)
    {
    }
    public function globalNs($param = GlobalNsClass::A)
    {
    }
    public function slashedNs($param = \Go\ParserReflection\Stub\ClassWithScalarConstants::A)
    {
    }
    public function ns($param = Go\ParserReflection\Stub\ClassWithScalarConstants::A)
    {
    }
    public function useNs($param = ClassWithScalarConstants::A)
    {
    }
}
