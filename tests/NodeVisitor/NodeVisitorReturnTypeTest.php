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

namespace Go\ParserReflection\NodeVisitor;

use PhpParser\NodeVisitor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * PhpParser\NodeVisitor declares the return types of its methods in docblocks only. An implementation without a
 * native return type and without its own @return makes Symfony's DebugClassLoader report that the parent "might
 * add" a native return type, so every overridden visitor method documents its return type explicitly.
 */
final class NodeVisitorReturnTypeTest extends TestCase
{
    /**
     * @param class-string<NodeVisitor> $visitorClass
     */
    #[DataProvider('visitorMethods')]
    public function testOverriddenVisitorMethodsDocumentTheirReturnType(string $visitorClass, string $methodName): void
    {
        $method = new ReflectionClass($visitorClass)->getMethod($methodName);
        $this->assertFalse($method->hasReturnType(), 'The return type is documented, not declared: PhpParser\NodeVisitor owns it');

        $docComment = $method->getDocComment();
        $this->assertIsString($docComment);
        $this->assertMatchesRegularExpression('/@return\s+\S+/', $docComment);
    }

    /**
     * @return iterable<string, array{class-string<NodeVisitor>, string}>
     */
    public static function visitorMethods(): iterable
    {
        $visitorMethods = array_map(
            static fn (\ReflectionMethod $method): string => $method->getName(),
            new ReflectionClass(NodeVisitor::class)->getMethods()
        );
        foreach (glob(dirname(__DIR__, 2) . '/src/NodeVisitor/*.php') ?: [] as $file) {
            /** @var class-string $visitorClass */
            $visitorClass = __NAMESPACE__ . '\\' . basename($file, '.php');
            $visitor      = new ReflectionClass($visitorClass);
            if (!$visitor->implementsInterface(NodeVisitor::class)) {
                continue;
            }
            foreach ($visitorMethods as $methodName) {
                if ($visitor->getMethod($methodName)->getDeclaringClass()->getName() === $visitorClass) {
                    yield $visitor->getShortName() . '::' . $methodName => [$visitorClass, $methodName];
                }
            }
        }
    }
}
