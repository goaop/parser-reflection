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

class NoInstantiable
{
    private function __construct() {}
}

interface AbstractInterface
{
    public function foo();
    public function bar();
}

class ClassWithScalarConstants
{
    const A = 10, A1 = 11;
    const B = 42.0;
    const C = 'foo';
    const D = false;
}

class ClassWithMagicConstants
{
    const A = __DIR__;
    const B = __FILE__;
    const C = __NAMESPACE__;
    const D = __CLASS__;
    const E = __LINE__;
}

const NS_CONST = 'test';

class ClassWithComplexConstantsAndInheritance extends ClassWithMagicConstants
{
    const A = 'overridden';
    const H = M_PI;
    const J = NS_CONST;
    const K = array(1, NS_CONST);
    const L = [self::class, ClassWithScalarConstants::A1, parent::A];
}