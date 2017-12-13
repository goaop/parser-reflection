<?php
namespace Go\ParserReflection\NodeVisitor;

class BuiltinTypeFixerTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @expectedException        InvalidArgumentException
     * @expectedExceptionMessage Option 'supportedBuiltinTypeHints' must be an array.
     */
    public function testSupportedBuiltinTypeHintsNotArray()
    {
        new BuiltinTypeFixer([
            'supportedBuiltinTypeHints' => 'int'
        ]);
    }

    /**
     * @expectedException        InvalidArgumentException
     * @expectedExceptionMessage Option 'supportedBuiltinTypeHints's element 'too many words' isn't a valid typehint string.
     */
    public function testSupportedBuiltinTypeHintsNotValidKeyword()
    {
        new BuiltinTypeFixer([
            'supportedBuiltinTypeHints' => ['too many words']
        ]);
    }

    /**
     * @expectedException        InvalidArgumentException
     * @expectedExceptionMessage Option 'supportedBuiltinTypeHints's element 'too many words' isn't a valid typehint string.
     */
    public function testSupportedBuiltinTypeHintsKeyNotValidKeyword()
    {
        new BuiltinTypeFixer([
            'supportedBuiltinTypeHints' => ['too many words' => BuiltinTypeFixer::PARAMETER_TYPES]
        ]);
    }

    /**
     * @expectedException        InvalidArgumentException
     * @expectedExceptionMessage Option 'supportedBuiltinTypeHints's 'int' typehint applies to invalid mask 75. Mask must be one of: Go\ParserReflection\NodeVisitor\BuiltinTypeFixer::PARAMETER_TYPES (1), Go\ParserReflection\NodeVisitor\BuiltinTypeFixer::RETURN_TYPES (2) or Go\ParserReflection\NodeVisitor\BuiltinTypeFixer::PARAMETER_TYPES|Go\ParserReflection\NodeVisitor\BuiltinTypeFixer::RETURN_TYPES (3)
     */
    public function testSupportedBuiltinTypeHintsInvalidMask()
    {
        new BuiltinTypeFixer([
            'supportedBuiltinTypeHints' => ['int' => 75]
        ]);
    }
}
