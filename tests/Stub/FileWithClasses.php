<?php

namespace ParserReflection\Stub;

abstract class ExplicitAbstractClass {}

abstract class ImplicitAbstractClass
{
    abstract function test();
}

final class FinalClass {}

interface SimpleInterface {}

trait SimpleTrait
{
    function foo() { return __CLASS__; }
}

class SimpleInheritance extends ExplicitAbstractClass {}

abstract class SimpleAbstractInheritance extends ImplicitAbstractClass {}

class ClassWithInterface implements SimpleInterface {}

class ClassWithTrait
{
    use SimpleTrait;
}