<?php
declare(strict_types=1);
/**
 * Parser Reflection API
 *
 * @copyright Copyright 2026, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\ParserReflection;

use Go\ParserReflection\Stub\ClassForLazyObjects;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the inherited \ReflectionClassConstant methods on enum cases and for the PHP 8.4 lazy-object API
 *
 * @see ReflectionEnumUnitCase
 * @see ReflectionEnumBackedCase
 * @see \Go\ParserReflection\Traits\ReflectionClassLikeTrait
 */
class EnumCaseAndLazyObjectTest extends TestCase
{
    private const STUB_FILENAME = '/Stub/FileWithEnumCases84.php';

    private const BACKED_ENUM_NAME = 'Go\ParserReflection\Stub\EnumCaseSuit';

    private const UNIT_ENUM_NAME = 'Go\ParserReflection\Stub\EnumCaseDirection';

    private ReflectionFileNamespace $parsedRefFileNamespace;

    protected function setUp(): void
    {
        $fileName = stream_resolve_include_path(__DIR__ . self::STUB_FILENAME);
        $fileNode = ReflectionEngine::parseFile($fileName);

        $reflectionFile = new ReflectionFile($fileName, $fileNode);

        $this->parsedRefFileNamespace = $reflectionFile->getFileNamespace('Go\ParserReflection\Stub');
    }

    /**
     * Enum cases must answer the inherited constant methods from the AST only, without loading the enum
     */
    public function testEnumCaseMethodsAreResolvedWithoutLoadingEnum(): void
    {
        $this->assertFalse(enum_exists(self::BACKED_ENUM_NAME, false), 'Backed enum should not be loaded yet');
        $this->assertFalse(enum_exists(self::UNIT_ENUM_NAME, false), 'Unit enum should not be loaded yet');

        $parsedBackedEnum = $this->getParsedEnum(self::BACKED_ENUM_NAME);

        $deprecatedBackedCase = $parsedBackedEnum->getCase('Hearts');
        $this->assertInstanceOf(ReflectionEnumBackedCase::class, $deprecatedBackedCase);
        $this->assertSame(\ReflectionClassConstant::IS_PUBLIC, $deprecatedBackedCase->getModifiers());
        $this->assertFalse($deprecatedBackedCase->hasType());
        $this->assertNull($deprecatedBackedCase->getType());
        $this->assertTrue($deprecatedBackedCase->isDeprecated());

        $plainBackedCase = $parsedBackedEnum->getCase('Spades');
        $this->assertSame(\ReflectionClassConstant::IS_PUBLIC, $plainBackedCase->getModifiers());
        $this->assertFalse($plainBackedCase->hasType());
        $this->assertNull($plainBackedCase->getType());
        $this->assertFalse($plainBackedCase->isDeprecated());

        $parsedUnitEnum = $this->getParsedEnum(self::UNIT_ENUM_NAME);

        $plainUnitCase = $parsedUnitEnum->getCase('Up');
        $this->assertInstanceOf(ReflectionEnumUnitCase::class, $plainUnitCase);
        $this->assertSame(\ReflectionClassConstant::IS_PUBLIC, $plainUnitCase->getModifiers());
        $this->assertFalse($plainUnitCase->hasType());
        $this->assertNull($plainUnitCase->getType());
        $this->assertFalse($plainUnitCase->isDeprecated());

        $deprecatedUnitCase = $parsedUnitEnum->getCase('Down');
        $this->assertSame(\ReflectionClassConstant::IS_PUBLIC, $deprecatedUnitCase->getModifiers());
        $this->assertTrue($deprecatedUnitCase->isDeprecated());

        $this->assertFalse(enum_exists(self::BACKED_ENUM_NAME, false), 'Backed enum should still not be loaded');
        $this->assertFalse(enum_exists(self::UNIT_ENUM_NAME, false), 'Unit enum should still not be loaded');
    }

    /**
     * Parsed enum cases should give exactly the same answers as the native reflection for a loaded enum
     */
    public function testEnumCaseMethodsMatchNativeReflection(): void
    {
        $this->loadStubFile();

        foreach ([self::BACKED_ENUM_NAME, self::UNIT_ENUM_NAME] as $enumName) {
            $parsedEnum = $this->getParsedEnum($enumName);
            /** @var \ReflectionEnum<\UnitEnum> $nativeEnum */
            $nativeEnum = new \ReflectionEnum($enumName);

            foreach ($nativeEnum->getCases() as $nativeCase) {
                $caseName   = $nativeCase->getName();
                $parsedCase = $parsedEnum->getCase($caseName);
                $message    = $enumName . '::' . $caseName;

                $this->assertSame($nativeCase->getModifiers(), $parsedCase->getModifiers(), $message);
                $this->assertSame($nativeCase->hasType(), $parsedCase->hasType(), $message);
                $this->assertSame($nativeCase->getType(), $parsedCase->getType(), $message);
                $this->assertSame($nativeCase->isDeprecated(), $parsedCase->isDeprecated(), $message);
            }
        }
    }

    /**
     * Lazy ghosts created from a parsed class should behave exactly like the native ones
     */
    public function testNewLazyGhost(): void
    {
        $this->loadStubFile();

        $parsedClass = $this->parsedRefFileNamespace->getClass(ClassForLazyObjects::class);
        $nativeClass = new \ReflectionClass(ClassForLazyObjects::class);

        $initializer = static function (ClassForLazyObjects $instance): void {
            $instance->value = 42;
            $instance->title = 'initialized';
        };

        $parsedGhost = $parsedClass->newLazyGhost($initializer);
        $nativeGhost = $nativeClass->newLazyGhost($initializer);

        $this->assertInstanceOf(ClassForLazyObjects::class, $parsedGhost);
        $this->assertTrue($parsedClass->isUninitializedLazyObject($parsedGhost));
        $this->assertSame($nativeClass->isUninitializedLazyObject($nativeGhost), $parsedClass->isUninitializedLazyObject($parsedGhost));
        $this->assertIsCallable($parsedClass->getLazyInitializer($parsedGhost));

        $this->assertSame($parsedGhost, $parsedClass->initializeLazyObject($parsedGhost));
        $this->assertFalse($parsedClass->isUninitializedLazyObject($parsedGhost));
        $this->assertNull($parsedClass->getLazyInitializer($parsedGhost));
        $this->assertSame(42, $parsedGhost->value);
        $this->assertSame('initialized', $parsedGhost->title);
    }

    /**
     * Lazy proxies created from a parsed class should delegate to the real instance built by the factory
     */
    public function testNewLazyProxy(): void
    {
        $this->loadStubFile();

        $parsedClass = $this->parsedRefFileNamespace->getClass(ClassForLazyObjects::class);

        $parsedProxy = $parsedClass->newLazyProxy(static fn(): ClassForLazyObjects => new ClassForLazyObjects(7));

        $this->assertInstanceOf(ClassForLazyObjects::class, $parsedProxy);
        $this->assertTrue($parsedClass->isUninitializedLazyObject($parsedProxy));

        $realInstance = $parsedClass->initializeLazyObject($parsedProxy);
        $this->assertFalse($parsedClass->isUninitializedLazyObject($parsedProxy));
        $this->assertSame(7, $realInstance->value);
        $this->assertSame(7, $parsedProxy->value);
    }

    /**
     * An existing instance should be resettable to a lazy ghost or to a lazy proxy
     */
    public function testResetAsLazyGhostAndProxy(): void
    {
        $this->loadStubFile();

        $parsedClass = $this->parsedRefFileNamespace->getClass(ClassForLazyObjects::class);

        $ghostCandidate = new ClassForLazyObjects(5);
        $this->assertFalse($parsedClass->isUninitializedLazyObject($ghostCandidate));
        $this->assertNull($parsedClass->getLazyInitializer($ghostCandidate));

        $parsedClass->resetAsLazyGhost($ghostCandidate, static function (ClassForLazyObjects $instance): void {
            $instance->value = 99;
        });
        $this->assertTrue($parsedClass->isUninitializedLazyObject($ghostCandidate));
        $this->assertSame(99, $ghostCandidate->value);
        $this->assertFalse($parsedClass->isUninitializedLazyObject($ghostCandidate));

        $proxyCandidate = new ClassForLazyObjects(5);
        $parsedClass->resetAsLazyProxy($proxyCandidate, static fn(): ClassForLazyObjects => new ClassForLazyObjects(3));
        $this->assertTrue($parsedClass->isUninitializedLazyObject($proxyCandidate));
        $this->assertSame(3, $proxyCandidate->value);
        $this->assertFalse($parsedClass->isUninitializedLazyObject($proxyCandidate));
    }

    /**
     * Marking a lazy object as initialized should skip the initializer and keep declared default values
     */
    public function testMarkLazyObjectAsInitialized(): void
    {
        $this->loadStubFile();

        $parsedClass = $this->parsedRefFileNamespace->getClass(ClassForLazyObjects::class);

        $parsedGhost = $parsedClass->newLazyGhost(static function (ClassForLazyObjects $instance): void {
            $instance->value = 1000;
        });

        $this->assertSame($parsedGhost, $parsedClass->markLazyObjectAsInitialized($parsedGhost));
        $this->assertFalse($parsedClass->isUninitializedLazyObject($parsedGhost));
        $this->assertSame(0, $parsedGhost->value);
        $this->assertSame('untouched', $parsedGhost->title);
    }

    private function getParsedEnum(string $enumName): ReflectionEnum
    {
        $parsedEnums = $this->parsedRefFileNamespace->getEnums();
        $this->assertArrayHasKey($enumName, $parsedEnums);

        return $parsedEnums[$enumName];
    }

    private function loadStubFile(): void
    {
        include_once stream_resolve_include_path(__DIR__ . self::STUB_FILENAME);
    }
}
