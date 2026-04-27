<?php

namespace Go\ParserReflection\Stub;

class ClassWithPhp56ArrayConstants
{
    const A = [10, 11];
    const B = array(42.0, 43.0);
}

const NS_CONST56 = 'test';

class ClassWithPhp56ComplexConstantsAndInheritance extends ClassWithPhp56ArrayConstants
{
    const K = array(1, NS_CONST56);
    const L = [self::class, ClassWithPhp56ArrayConstants::A, parent::B, self::A];
    const M = \DateTime::ATOM;
}

const ONE = 1;

/**
 * The following operations are currently supported for scalar expressions:
 *
 * + - Addition
 * - - Subtraction
 * * - Multiplication
 * / - Division
 * % - Modulus
 * ! - Boolean Negation
 * ~ - Bitwise Negation
 * | - Bitwise OR
 * & - Bitwise AND
 * ^ - Bitwise XOR
 * << - Bitwise Shift Left
 * >> - Bitwise Shift Right
 * . - Concatenation
 * ?: - Ternary Operator
 * <= - Smaller or Equal
 * => - Greater or Equal
 * == - Equal
 * != - Not Equal
 * < - Smaller
 * > - Greater
 * === - Identical
 * !== - Not Identical
 * && / and - Boolean AND
 * || / or - Boolean OR
 * xor - Boolean XOR
 *
 * Also supported is grouping static operations: (1 + 2) * 3.
 *
 * @see https://wiki.php.net/rfc/const_scalar_exprs
 */
class ClassWithPhp56ConstantExpressions
{
    const ADDITION            = ONE + 1;
    const SUBTRACTION         = 2 - ONE;
    const MULTIPLICATION      = ONE * 2;
    const DIVISION            = ONE / 2;
    const MODULUS             = ONE % 2;
    const BOOLEAN_NEGATION    = !ONE;
    const BITWISE_NEGATION    = ~ONE;
    const BITWISE_OR          = ONE | 2;
    const BITWISE_AND         = ONE & 2;
    const BITWISE_XOR         = ONE ^ 2;
    const BITWISE_SHIFT_LEFT  = ONE << 3;
    const BITWISE_SHIFT_RIGHT = ONE >> 3;
    const CONCATENATION       = 'Value of shift left is ' . self::BITWISE_SHIFT_LEFT;
    const TERNARY_OPERATOR    = true ?: false;
    const SMALLER_OR_EQUAL    = ONE <= 2;
    const GREATER_OR_EQUAL    = ONE >= 2;
    const EQUAL               = ONE == true;
    const NOT_EQUAL           = ONE != 2;
    const SMALLER             = ONE < 2;
    const GREATER             = ONE > 2;
    const IDENTICAL           = ONE === true;
    const NOT_IDENTICAL       = ONE !== true;
    const BOOLEAN_AND         = ONE && false;
    const LOGICAL_AND         = ONE and false;
    const BOOLEAN_OR          = ONE || true;
    const LOGICAL_OR          = ONE or true;
    const LOGICAL_XOR         = ONE xor true;
    const GROUPING            = (ONE + 2) * 3;

    const REFERENCE = self::ADDITION;
}
