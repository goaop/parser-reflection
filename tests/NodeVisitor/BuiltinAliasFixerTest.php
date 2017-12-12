<?php
namespace Go\ParserReflection\NodeVisitor;

class BuiltinAliasFixerTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @expectedException        InvalidArgumentException
     * @expectedExceptionMessage Option 'supportedBuiltinTypeHints' must be an array.
     */
    public function testSupportedBuiltinTypeHintsNotArray()
    {
        new BuiltinAliasFixer([
            'supportedBuiltinTypeHints' => 'int'
        ]);
    }

    /**
     * @expectedException        InvalidArgumentException
     * @expectedExceptionMessage Option 'supportedBuiltinTypeHints's element 'too many words' isn't a valid typehint string.
     */
    public function testSupportedBuiltinTypeHintsNotValidKeyword()
    {
        new BuiltinAliasFixer([
            'supportedBuiltinTypeHints' => ['too many words']
        ]);
    }

    /**
     * @expectedException        InvalidArgumentException
     * @expectedExceptionMessage Option 'supportedBuiltinTypeHints's element 'too many words' isn't a valid typehint string.
     */
    public function testSupportedBuiltinTypeHintsKeyNotValidKeyword()
    {
        new BuiltinAliasFixer([
            'supportedBuiltinTypeHints' => ['too many words' => BuiltinAliasFixer::PARAMETER_TYPES]
        ]);
    }

    /**
     * @expectedException        InvalidArgumentException
     * @expectedExceptionMessage Option 'supportedBuiltinTypeHints's 'int' typehint applies to invalid mask 75. Mask must be one of: Go\ParserReflection\NodeVisitor\BuiltinAliasFixer::PARAMETER_TYPES (1), Go\ParserReflection\NodeVisitor\BuiltinAliasFixer::RETURN_TYPES (2) or Go\ParserReflection\NodeVisitor\BuiltinAliasFixer::PARAMETER_TYPES|Go\ParserReflection\NodeVisitor\BuiltinAliasFixer::RETURN_TYPES (3)
     */
    public function testSupportedBuiltinTypeHintsInvalidMask()
    {
        new BuiltinAliasFixer([
            'supportedBuiltinTypeHints' => ['int' => 75]
        ]);
    }
}
