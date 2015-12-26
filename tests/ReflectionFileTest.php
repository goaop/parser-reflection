<?php
namespace Go\ParserReflection;

class ReflectionFileTest extends \PHPUnit_Framework_TestCase
{
    const STUB_FILE        = '/Stub/FileWithNamespaces.php';
    const STUB_GLOBAL_FILE = '/Stub/FileWithGlobalNamespace.php';

    /**
     * @var ReflectionFile
     */
    protected $parsedRefFile;

    protected function setUp()
    {
        $fileName       = stream_resolve_include_path(__DIR__ . self::STUB_FILE);
        $reflectionFile = new ReflectionFile($fileName);

        $this->parsedRefFile = $reflectionFile;
    }

    public function testGetName()
    {
        $fileName     = $this->parsedRefFile->getName();
        $expectedName = stream_resolve_include_path(__DIR__ . self::STUB_FILE);
        $this->assertEquals($expectedName, $fileName);
    }

    public function testGetFileNamespaces()
    {
        $reflectionFileNamespaces = $this->parsedRefFile->getFileNamespaces();
        $this->assertCount(3, $reflectionFileNamespaces);
    }

    public function testGetFileNamespace()
    {
        $reflectionFileNamespace = $this->parsedRefFile->getFileNamespace('Go\ParserReflection\Stub');
        $this->assertInstanceOf(ReflectionFileNamespace::class, $reflectionFileNamespace);

        $reflectionFileNamespace = $this->parsedRefFile->getFileNamespace('Unknown');
        $this->assertFalse($reflectionFileNamespace);
    }

    public function testHasFileNamespace()
    {
        $hasFileNamespace = $this->parsedRefFile->hasFileNamespace('Go\ParserReflection\Stub');
        $this->assertTrue($hasFileNamespace);

        $hasFileNamespace = $this->parsedRefFile->hasFileNamespace('Unknown');
        $this->assertFalse($hasFileNamespace);
    }

    public function testGetGlobalFileNamespace()
    {
        $fileName       = stream_resolve_include_path(__DIR__ . self::STUB_GLOBAL_FILE);
        $reflectionFile = new ReflectionFile($fileName);

        $reflectionFileNamespace = $reflectionFile->getFileNamespace('\\');
        $this->assertInstanceOf(ReflectionFileNamespace::class, $reflectionFileNamespace);
    }
}
