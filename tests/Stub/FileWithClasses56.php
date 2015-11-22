<?php

namespace Go\ParserReflection\Stub;

class ClassWithArrayConstants
{
    const A = [10, 11];
    const B = array(42.0, 43.0);
}

const NS_CONST56 = 'test';

class ClassWithComplexConstantsAndInheritance extends ClassWithArrayConstants
{
    const K = array(1, NS_CONST56);
    const L = [self::class, ClassWithArrayConstants::A, parent::B];
}
