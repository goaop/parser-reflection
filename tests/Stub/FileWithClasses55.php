<?php

namespace Go\ParserReflection\Stub;

/**
 * @see https://www.php.net/manual/en/language.oop5.abstract.php
 */
abstract class ClassWithPhp50ExplicitAbstractKeyword {}

/**
 * @see https://www.php.net/manual/en/language.oop5.abstract.php
 */
abstract class ClassWithPhp50ImplicitAbstractKeyword
{
    private $a = 'foo';
    protected $b = 'bar';
    public $c = 'baz';

    abstract function test();
}

/**
 * @see https://www.php.net/manual/en/language.oop5.final.php
 */
final class ClassWithPhp50FinalKeyword
{
    public $args = [];
    public function __construct($a = null, &$b = null)
    {
        $this->args = array_slice(array($a, &$b), 0, func_num_args());
    }
}

class SimplePhp50BaseClass
{
    protected static function prototypeMethod()
    {
        return __CLASS__;
    }
}

/**
 * @link https://bugs.php.net/bug.php?id=70957 self::class can not be resolved with reflection for abstract class
 */
abstract class SimplePhp50AbstractClassWithMethods extends SimplePhp50BaseClass
{
    public const TEST = 5;

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

    private function testParam($a, $b = null, $d = self::TEST, $d2 = self::TEST . '_append', $e = M_PI, $f = \M_PI, $g = ['test'], $h = false, $i = true, $j = array('yoo')) {}
}

class SimplePhp50ClassWithProperties
{
    private $privateProperty = 123;
    protected $protectedProperty = 'a';
    public $publicProperty = 42.0;

    /**
     * Some message to test docBlock
     *
     * @var int
     */
    private static $privateStaticProperty = 1;
    protected static $protectedStaticProperty = 'foo';
    public static $publicStaticProperty = M_PI;
}

abstract class SimplePhp50ClassWithMethodsAndProperties
{
    public $publicProperty;
    protected $protectedProperty;
    private $privateProperty;

    static public $staticPublicProperty;
    static protected $staticProtectedProperty;
    static private $staticPrivateProperty;

    public function publicMethod() {}
    protected function protectedMethod() {}
    private function privateMethod() {}

    static public function publicStaticMethod() {}
    static protected function protectedStaticMethod() {}
    static private function privateStaticMethod() {}

    abstract public function publicAbstractMethod();
    abstract protected function protectedAbstractMethod();

    final public function publicFinalMethod() {}
    final protected function protectedFinalMethod() {}
}

/**
 * @see https://www.php.net/manual/en/language.oop5.interfaces.php
 */
interface SimplePhp50Interface {}

interface SimplePhp50InterfaceWithMethod {
    function foo();
}

/**
 * @see https://www.php.net/manual/en/language.oop5.traits.php
 */
trait SimplePhp54Trait
{
    function foo() { return __CLASS__; }
}

trait SimplePhp54ConflictedTrait
{
    function foo() { return 'BAZ'; }
}

class SimplePhp50ClassInheritance extends ClassWithPhp50ExplicitAbstractKeyword {}

abstract class SimplePhp50AbstractClassInheritance extends ClassWithPhp50ImplicitAbstractKeyword
{
    public $b = 'bar1';
    public $d = 'foobar';
    private $e = 'foobaz';
}

class ClassWithPhp50Interface implements SimplePhp50Interface {}

class ClassWithPhp54Trait
{
    use SimplePhp54Trait;
}

/*
 * Current implementation doesn't support trait adaptation,
 * @see https://github.com/goaop/parser-reflection/issues/54
 *
class ClassWithTraitAndAdaptation
{
    use SimpleTrait {
        foo as protected fooBar;
        foo as private fooBaz;
    }
}

class ClassWithTraitAndConflict
{
    use SimpleTrait, ConflictedSimpleTrait {
        foo as protected fooBar;
        ConflictedSimpleTrait::foo insteadof SimpleTrait;
    }
}
*/

/*
 * Logic of prototype methods for interface and traits was changed since 7.0.6
 * @see https://github.com/goaop/parser-reflection/issues/56

class ClassWithTraitAndInterface implements SimplePhp50InterfaceWithMethod
{
    use SimplePhp54Trait;
}
*/

class SimplePhp50NoCloneableClass
{
    private function __clone() {}
}

class SimplePhp50NoInstantiableClass
{
    private function __construct() {}
}

class ClassWithPhp50ScalarConstants
{
    const A = 10, A1 = 11;
    const B = 42.0;
    const C = 'foo';
    const D = false;
    const E = null;
}

class ClassWithPhp50MagicConstants
{
    const A = __DIR__;
    const B = __FILE__;
    const C = __NAMESPACE__;
    const D = __CLASS__;
    const E = __LINE__;

    public static $a    = self::A;
    protected static $b = self::B;
    private static $c   = self::C;
}

const NS_CONST = 'test';

class ClassWithPhp50ConstantsAndInheritance extends ClassWithPhp50MagicConstants
{
    const A = 'overridden';
    const H = M_PI;
    const J = NS_CONST;

    public static $h = self::H;
}

trait TraitWithPhp54Properties
{
    private $a = 'foo';
    protected $b = 'bar';
    public $c = 'baz';

    private static $as = 1;
    protected static $bs = __TRAIT__;
    public static $cs = 'foo';
}
