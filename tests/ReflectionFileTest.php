<?php
declare(strict_types=1);

namespace Go\ParserReflection;

use PHPUnit\Framework\TestCase;
use Stub\Issue44\Locator;
use TypeError;

class ReflectionFileTest extends TestCase
{
    public const STUB_FILE        = '/Stub/FileWithNamespaces.php';
    public const STUB_GLOBAL_FILE = '/Stub/FileWithGlobalNamespace.php';
    protected ReflectionFile $parsedRefFile;

    protected function setUp(): void
    {
        $fileName       = stream_resolve_include_path(__DIR__ . self::STUB_FILE);
        $reflectionFile = new ReflectionFile($fileName);

        $this->parsedRefFile = $reflectionFile;
    }

    public function testGetName(): void
    {
        $fileName     = $this->parsedRefFile->getName();
        $expectedName = stream_resolve_include_path(__DIR__ . self::STUB_FILE);
        $this->assertEquals($expectedName, $fileName);
    }

    public function testGetFileNamespaces(): void
    {
        $reflectionFileNamespaces = $this->parsedRefFile->getFileNamespaces();
        $this->assertCount(3, $reflectionFileNamespaces);
    }

    public function testGetFileNamespace(): void
    {
        $reflectionFileNamespace = $this->parsedRefFile->getFileNamespace('Go\ParserReflection\Stub');
        $this->assertInstanceOf(ReflectionFileNamespace::class, $reflectionFileNamespace);

        $this->expectException(ReflectionException::class);
        $this->expectExceptionMessageMatches('/^Could not find the namespace Unknown in the file/');
        $this->parsedRefFile->getFileNamespace('Unknown');
    }

    public function testHasFileNamespace(): void
    {
        $hasFileNamespace = $this->parsedRefFile->hasFileNamespace('Go\ParserReflection\Stub');
        $this->assertTrue($hasFileNamespace);

        $hasFileNamespace = $this->parsedRefFile->hasFileNamespace('Unknown');
        $this->assertFalse($hasFileNamespace);
    }

    public function testGetGlobalFileNamespace(): void
    {
        $fileName       = stream_resolve_include_path(__DIR__ . self::STUB_GLOBAL_FILE);
        $reflectionFile = new ReflectionFile($fileName);

        $reflectionFileNamespace = $reflectionFile->getFileNamespace('');
        $this->assertInstanceOf(ReflectionFileNamespace::class, $reflectionFileNamespace);
    }

    /**
     * Tests if strict mode detected correctly
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('fileNameProvider')]
    public function testIsStrictType(string $fileName, bool $shouldBeStrict): void
    {
        $fileName       = stream_resolve_include_path(__DIR__ . $fileName);
        $reflectionFile = new ReflectionFile($fileName);

        $this->assertSame($shouldBeStrict, $reflectionFile->isStrictMode());
    }

    public static function fileNameProvider(): \Iterator
    {
        yield '/Stub/FileWithClasses56.php' => ['/Stub/FileWithClasses56.php', false];
        yield '/Stub/FileWithClasses70.php' => ['/Stub/FileWithClasses70.php', false];
        yield '/Stub/FileWithClasses71.php' => ['/Stub/FileWithClasses71.php', true];
        yield '/Stub/FileWithGlobalNamespace.php' => ['/Stub/FileWithGlobalNamespace.php', true];
    }

    public function testGetInterfaceNamesWithExtends(): void
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
        $this->assertSame([], $interfaceNames);
    }
}
