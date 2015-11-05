<?php

namespace ParserReflection\Stub;

abstract class ExplicitAbstractClass {}

abstract class ImplicitAbstractClass
{
    abstract function test();
}

final class FinalClass {}

interface SimpleInterface {}

interface InterfaceWithMethod {
    function foo();
}

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

class ClassWithTraitAndInterface implements InterfaceWithMethod
{
    use SimpleTrait;
}

class NoCloneable
{
    private function __clone() {}
}

interface AbstractInterface
{
    public function foo();
    public function bar();
}