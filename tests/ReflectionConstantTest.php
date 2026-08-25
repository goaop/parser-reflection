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

use Deprecated;
use PhpParser\Node\Attribute;
use PhpParser\Node\Const_;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the AST-based reflection of global and namespaced constants
 *
 * @see ReflectionConstant
 */
class ReflectionConstantTest extends TestCase
{
    private const STUB_FILENAME = '/Stub/FileWithGlobalConstants84.php';

    private const ATTRIBUTED_STUB_FILENAME = '/Stub/FileWithAttributedConstants85.php';

    private const STUB_NAMESPACE = 'Go\ParserReflection\Stub';

    /**
     * List of constants from the stub file with their expected values
     *
     * @var array<string, mixed>
     */
    private const EXPECTED_CONSTANTS = [
        'GLOBAL_CONSTANT_INT'        => 42,
        'GLOBAL_CONSTANT_EXPRESSION' => 7,
        'GLOBAL_CONSTANT_STRING'     => 'parser-reflection',
        'GLOBAL_CONSTANT_ARRAY'      => [1, 2, 3],
        'GLOBAL_CONSTANT_FIRST'      => 1,
        'GLOBAL_CONSTANT_SECOND'     => 2,
    ];

    private ReflectionFileNamespace $parsedRefFileNamespace;

    protected function setUp(): void
    {
        $this->parsedRefFileNamespace = $this->getParsedFileNamespace(self::STUB_FILENAME);
    }

    /**
     * Constants should be resolved from the AST only, without including the file with them
     */
    public function testConstantsAreResolvedWithoutLoadingFile(): void
    {
        foreach (array_keys(self::EXPECTED_CONSTANTS) as $shortName) {
            $this->assertFalse(
                defined(self::STUB_NAMESPACE . '\\' . $shortName),
                'Stub constant ' . $shortName . ' should not be defined yet'
            );
        }

        $parsedConstants = $this->parsedRefFileNamespace->getReflectionConstants();
        $this->assertSame(array_keys(self::EXPECTED_CONSTANTS), array_keys($parsedConstants));

        foreach (self::EXPECTED_CONSTANTS as $shortName => $expectedValue) {
            $parsedConstant = $parsedConstants[$shortName];

            $this->assertInstanceOf(ReflectionConstant::class, $parsedConstant);
            $this->assertSame(self::STUB_NAMESPACE . '\\' . $shortName, $parsedConstant->getName());
            $this->assertSame(self::STUB_NAMESPACE . '\\' . $shortName, $parsedConstant->name);
            $this->assertSame($shortName, $parsedConstant->getShortName());
            $this->assertSame(self::STUB_NAMESPACE, $parsedConstant->getNamespaceName());
            $this->assertTrue($parsedConstant->inNamespace());
            $this->assertSame($expectedValue, $parsedConstant->getValue());
            $this->assertFalse($parsedConstant->isDeprecated());
            $this->assertSame([], $parsedConstant->getAttributes());
            $this->assertInstanceOf(Const_::class, $parsedConstant->getNode());
        }

        foreach (array_keys(self::EXPECTED_CONSTANTS) as $shortName) {
            $this->assertFalse(
                defined(self::STUB_NAMESPACE . '\\' . $shortName),
                'Stub constant ' . $shortName . ' should still not be defined'
            );
        }
    }

    /**
     * Textual representation should follow the native format even without loading the file
     */
    public function testStringRepresentationIsResolvedWithoutLoadingFile(): void
    {
        $parsedConstant = $this->parsedRefFileNamespace->getReflectionConstant('GLOBAL_CONSTANT_INT');
        $this->assertInstanceOf(ReflectionConstant::class, $parsedConstant);
        $this->assertSame(
            "Constant [ int " . self::STUB_NAMESPACE . "\\GLOBAL_CONSTANT_INT ] { 42 }\n",
            (string) $parsedConstant
        );

        $parsedArrayConstant = $this->parsedRefFileNamespace->getReflectionConstant('GLOBAL_CONSTANT_ARRAY');
        $this->assertInstanceOf(ReflectionConstant::class, $parsedArrayConstant);
        $this->assertSame(
            "Constant [ array " . self::STUB_NAMESPACE . "\\GLOBAL_CONSTANT_ARRAY ] { Array }\n",
            (string) $parsedArrayConstant
        );
    }

    /**
     * Lookup of a single constant should be available by its short name only
     */
    public function testGetReflectionConstant(): void
    {
        $parsedConstant = $this->parsedRefFileNamespace->getReflectionConstant('GLOBAL_CONSTANT_SECOND');

        $this->assertInstanceOf(ReflectionConstant::class, $parsedConstant);
        $this->assertSame(2, $parsedConstant->getValue());
        $this->assertFalse($this->parsedRefFileNamespace->getReflectionConstant('UNKNOWN_CONSTANT'));
    }

    /**
     * Existing methods for constants should not be affected by the new reflection
     */
    public function testPlainConstantListIsNotAffected(): void
    {
        $this->assertSame(self::EXPECTED_CONSTANTS, $this->parsedRefFileNamespace->getConstants());
        $this->assertTrue($this->parsedRefFileNamespace->hasConstant('GLOBAL_CONSTANT_INT'));
        $this->assertSame(42, $this->parsedRefFileNamespace->getConstant('GLOBAL_CONSTANT_INT'));
    }

    /**
     * Constant can be also reflected by its name, when the namespace to search in is known
     */
    public function testConstantCanBeFoundByNameInGivenNamespace(): void
    {
        $parsedConstant = new ReflectionConstant(
            self::STUB_NAMESPACE . '\GLOBAL_CONSTANT_STRING',
            null,
            null,
            $this->parsedRefFileNamespace
        );

        $this->assertSame('GLOBAL_CONSTANT_STRING', $parsedConstant->getShortName());
        $this->assertSame('parser-reflection', $parsedConstant->getValue());
        $this->assertSame(['name' => self::STUB_NAMESPACE . '\GLOBAL_CONSTANT_STRING'], $parsedConstant->__debugInfo());
    }

    /**
     * Global constants can not be located by name alone, because there is no locator for them
     */
    public function testConstantWithoutNodesAndNamespaceIsRejected(): void
    {
        $this->expectException(ReflectionException::class);

        new ReflectionConstant(self::STUB_NAMESPACE . '\GLOBAL_CONSTANT_INT');
    }

    /**
     * Unknown constant should be reported as an error during the search in the namespace
     */
    public function testUnknownConstantIsRejected(): void
    {
        $this->expectException(ReflectionException::class);

        new ReflectionConstant(
            self::STUB_NAMESPACE . '\UNKNOWN_CONSTANT',
            null,
            null,
            $this->parsedRefFileNamespace
        );
    }

    /**
     * Parsed constants should give exactly the same answers as the native reflection for a loaded file
     */
    public function testConstantsMatchNativeReflection(): void
    {
        include_once stream_resolve_include_path(__DIR__ . self::STUB_FILENAME);

        foreach (array_keys(self::EXPECTED_CONSTANTS) as $shortName) {
            $constantName = self::STUB_NAMESPACE . '\\' . $shortName;

            $parsedConstant = $this->parsedRefFileNamespace->getReflectionConstant($shortName);
            $this->assertInstanceOf(ReflectionConstant::class, $parsedConstant);
            $nativeConstant = new \ReflectionConstant($constantName);

            $this->assertSame($nativeConstant->getName(), $parsedConstant->getName(), $constantName);
            $this->assertSame($nativeConstant->getShortName(), $parsedConstant->getShortName(), $constantName);
            $this->assertSame($nativeConstant->getNamespaceName(), $parsedConstant->getNamespaceName(), $constantName);
            if (PHP_VERSION_ID >= 80600) {
                // \ReflectionConstant::inNamespace() is available since PHP 8.6 only
                $this->assertSame($nativeConstant->inNamespace(), $parsedConstant->inNamespace(), $constantName);
            }
            $this->assertSame($nativeConstant->getValue(), $parsedConstant->getValue(), $constantName);
            $this->assertSame($nativeConstant->isDeprecated(), $parsedConstant->isDeprecated(), $constantName);
            $this->assertSame($nativeConstant->__toString(), $parsedConstant->__toString(), $constantName);
            $this->assertSame($nativeConstant->name, $parsedConstant->name, $constantName);
        }
    }

    /**
     * Attributes on constants are a PHP 8.5 feature, they should be resolved from the AST only
     */
    public function testAttributesOnConstantsAreResolvedStatically(): void
    {
        $parsedNamespace = $this->getParsedFileNamespace(self::ATTRIBUTED_STUB_FILENAME);

        $deprecatedConstant = $parsedNamespace->getReflectionConstant('ATTRIBUTED_CONSTANT_LEGACY');
        $this->assertInstanceOf(ReflectionConstant::class, $deprecatedConstant);
        $this->assertTrue($deprecatedConstant->isDeprecated());
        $this->assertSame('legacy', $deprecatedConstant->getValue());

        $attributes = $deprecatedConstant->getAttributes();
        $this->assertCount(1, $attributes);
        $this->assertInstanceOf(ReflectionAttribute::class, $attributes[0]);
        $this->assertSame(Deprecated::class, $attributes[0]->getName());
        // Named arguments keep their names, just like the native reflection reports them
        $this->assertSame(
            ['message' => 'Use ATTRIBUTED_CONSTANT_MODERN instead', 'since' => '8.5'],
            $attributes[0]->getArguments()
        );
        $this->assertFalse($attributes[0]->isRepeated());
        $this->assertInstanceOf(Attribute::class, $attributes[0]->getNode());

        $aliasedConstant = $parsedNamespace->getReflectionConstant('ATTRIBUTED_CONSTANT_ALIASED');
        $this->assertInstanceOf(ReflectionConstant::class, $aliasedConstant);
        $this->assertTrue($aliasedConstant->isDeprecated());
        $this->assertSame(
            [Deprecated::class],
            array_map(static fn(ReflectionAttribute $attribute): string => $attribute->getName(), $aliasedConstant->getAttributes())
        );

        $markedConstant = $parsedNamespace->getReflectionConstant('ATTRIBUTED_CONSTANT_MARKED');
        $this->assertInstanceOf(ReflectionConstant::class, $markedConstant);
        $this->assertFalse($markedConstant->isDeprecated());
        $markedAttributes = $markedConstant->getAttributes();
        $this->assertCount(1, $markedAttributes);
        $this->assertSame(self::STUB_NAMESPACE . '\ConstantMarker', $markedAttributes[0]->getName());
        $this->assertSame(['tag' => 'marked'], $markedAttributes[0]->getArguments());
        $this->assertSame([], $markedConstant->getAttributes(Deprecated::class));

        $modernConstant = $parsedNamespace->getReflectionConstant('ATTRIBUTED_CONSTANT_MODERN');
        $this->assertInstanceOf(ReflectionConstant::class, $modernConstant);
        $this->assertFalse($modernConstant->isDeprecated());
        $this->assertSame([], $modernConstant->getAttributes());

        $this->assertCount(1, $deprecatedConstant->getAttributes(Deprecated::class));
        $this->assertFalse(
            defined(self::STUB_NAMESPACE . '\ATTRIBUTED_CONSTANT_LEGACY'),
            'PHP 8.5 stub file should never be loaded'
        );
    }

    /**
     * Constants from the global namespace should not have any namespace prefix
     */
    public function testConstantInGlobalNamespace(): void
    {
        $parsedNamespace = $this->getParsedFileNamespace(self::ATTRIBUTED_STUB_FILENAME, '');

        $globalConstant = $parsedNamespace->getReflectionConstant('ATTRIBUTED_GLOBAL_CONSTANT');
        $this->assertInstanceOf(ReflectionConstant::class, $globalConstant);
        $this->assertSame('ATTRIBUTED_GLOBAL_CONSTANT', $globalConstant->getName());
        $this->assertSame('ATTRIBUTED_GLOBAL_CONSTANT', $globalConstant->getShortName());
        $this->assertSame('', $globalConstant->getNamespaceName());
        $this->assertFalse($globalConstant->inNamespace());
        $this->assertSame('global', $globalConstant->getValue());
        $this->assertTrue($globalConstant->isDeprecated());
        $this->assertFalse(
            defined('ATTRIBUTED_GLOBAL_CONSTANT'),
            'PHP 8.5 stub file should never be loaded'
        );
    }

    /**
     * Constants defined via "define(...)" are created at runtime and should not be reflected
     */
    public function testDefinedConstantsAreNotReflected(): void
    {
        $parsedNamespace = $this->getParsedFileNamespace('/Stub/FileWithGlobalNamespace.php', '');

        $this->assertSame([], $parsedNamespace->getReflectionConstants());
        $this->assertFalse($parsedNamespace->getReflectionConstant('INT_CONST'));
        $this->assertArrayHasKey('INT_CONST', $parsedNamespace->getConstants(true));
    }

    private function getParsedFileNamespace(string $stubFileName, string $namespaceName = self::STUB_NAMESPACE): ReflectionFileNamespace
    {
        $resolvedFileName = stream_resolve_include_path(__DIR__ . $stubFileName);
        $this->assertIsString($resolvedFileName, 'Stub file ' . $stubFileName . ' should be available');

        $reflectionFile = new ReflectionFile($resolvedFileName);

        return $reflectionFile->getFileNamespace($namespaceName);
    }
}
