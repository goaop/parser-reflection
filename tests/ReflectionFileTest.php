<?php
declare(strict_types=1);

namespace Go\ParserReflection;

use PHPUnit\Framework\TestCase;
use Stub\Issue44\Locator;
use TypeError;

class ReflectionFileTest extends TestCase
{
    const STUB_FILE        = '/Stub/FileWithNamespaces.php';
    const STUB_GLOBAL_FILE = '/Stub/FileWithGlobalNamespace.php';

    /**
     * @var ReflectionFile
     */
    protected $parsedRefFile;

    protected function setUp(): void
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

        $reflectionFileNamespace = $reflectionFile->getFileNamespace('');
        $this->assertInstanceOf(ReflectionFileNamespace::class, $reflectionFileNamespace);
    }

    /**
     * Tests if strict mode detected correctly
     *
     * @param string $fileName Filename to analyse
     * @param bool $shouldBeStrict
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('fileNameProvider')]
    public function testIsStrictType($fileName, $shouldBeStrict)
    {
        $fileName       = stream_resolve_include_path(__DIR__ . $fileName);
        $reflectionFile = new ReflectionFile($fileName);

        $this->assertSame($shouldBeStrict, $reflectionFile->isStrictMode());
    }

    public static function fileNameProvider()
    {
        return [
            '/Stub/FileWithClasses56.php'       => ['/Stub/FileWithClasses56.php', false],
            '/Stub/FileWithClasses70.php'       => ['/Stub/FileWithClasses70.php', false],
            '/Stub/FileWithClasses71.php'       => ['/Stub/FileWithClasses71.php', true],
            '/Stub/FileWithGlobalNamespace.php' => ['/Stub/FileWithGlobalNamespace.php', true],
        ];
    }

    public function testGetInterfaceNamesWithExtends()
    {
        $fileName = __DIR__ . '/Stub/Issue44/ClassWithoutNamespace.php';

        require_once __DIR__ . '/Stub/Issue44/Locator.php';
        ReflectionEngine::init(new Locator());

        $reflectedFile = new ReflectionFile($fileName);
        $namespaces = $reflectedFile->getFileNamespaces();
        $namespace = array_pop($namespaces);
        $classes = $namespace->getClasses();
        $class = array_pop($classes);

        $interfaceNames = $class->getInterfaceNames();
        $this->assertEquals([], $interfaceNames);
    }
}
