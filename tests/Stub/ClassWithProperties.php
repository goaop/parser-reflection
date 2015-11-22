<?php

namespace Go\ParserReflection\Stub;

class ClassWithProperties
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