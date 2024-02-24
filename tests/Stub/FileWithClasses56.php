<?php

namespace Go\ParserReflection\Stub;

class ClassWithArrayConstants
{
    public const A = [10, 11];
    public const B = array(42.0, 43.0);
}

const NS_CONST56 = 'test';

class ClassWithComplexConstantsAndInheritance extends ClassWithArrayConstants
{
    public const K = array(1, NS_CONST56);
    public const L = [self::class, ClassWithArrayConstants::A, parent::B, self::A];
    public const M = \DateTime::ATOM;
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
 */
class ClassWithConstantExpressions
{
    public const ADDITION            = ONE + 1;
    public const SUBTRACTION         = 2 - ONE;
    public const MULTIPLICATION      = ONE * 2;
    public const DIVISION            = ONE / 2;
    public const MODULUS             = ONE % 2;
    public const BOOLEAN_NEGATION    = !ONE;
    public const BITWISE_NEGATION    = ~ONE;
    public const BITWISE_OR          = ONE | 2;
    public const BITWISE_AND         = ONE & 2;
    public const BITWISE_XOR         = ONE ^ 2;
    public const BITWISE_SHIFT_LEFT  = ONE << 3;
    public const BITWISE_SHIFT_RIGHT = ONE >> 3;
    public const CONCATENATION       = 'Value of shift left is ' . self::BITWISE_SHIFT_LEFT;
    public const TERNARY_OPERATOR    = true ?: false;
    public const SMALLER_OR_EQUAL    = ONE <= 2;
    public const GREATER_OR_EQUAL    = ONE >= 2;
    public const EQUAL               = ONE == true;
    public const NOT_EQUAL           = ONE != 2;
    public const SMALLER             = ONE < 2;
    public const GREATER             = ONE > 2;
    public const IDENTICAL           = ONE === true;
    public const NOT_IDENTICAL       = ONE !== true;
    public const BOOLEAN_AND         = ONE && false;
    public const LOGICAL_AND         = ONE and false;
    public const BOOLEAN_OR          = ONE || true;
    public const LOGICAL_OR          = ONE or true;
    public const LOGICAL_XOR         = ONE xor true;
    public const GROUPING            = (ONE + 2) * 3;

    public const REFERENCE = self::ADDITION;
}
