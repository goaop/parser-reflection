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

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * A class declared by eval() reports a pseudo file name ("file.php(12) : eval()'d code"): reflecting it, or a class
 * inheriting from it, must not try to read that file
 */
final class EvalDeclaredParentTest extends TestCase
{
    private const string PARENT_CLASS = 'Go\ParserReflection\Stub\EvalDeclared\EvalDeclaredParent';
    private const string CHILD_CLASS  = 'Go\ParserReflection\Stub\EvalDeclared\ChildOfEvalDeclaredParent';

    public static function setUpBeforeClass(): void
    {
        if (!class_exists(self::PARENT_CLASS, false)) {
            eval('namespace Go\ParserReflection\Stub\EvalDeclared; class EvalDeclaredParent { public function inheritedMethod(): int { return 1; } }');
        }
    }

    public function testLocatingAnEvalDeclaredClassReportsItAsNotFound(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Class ' . self::PARENT_CLASS . ' is not declared in a file');

        ReflectionEngine::locateClassFile(self::PARENT_CLASS);
    }

    public function testMethodsInheritedFromAnEvalDeclaredParentAreReflectedWithoutWarnings(): void
    {
        $file   = new ReflectionFile(__DIR__ . '/Stub/FileWithEvalDeclaredParent.php');
        $class  = $file->getFileNamespace('Go\ParserReflection\Stub\EvalDeclared')->getClass(self::CHILD_CLASS);
        $errors = [];
        set_error_handler(static function (int $type, string $message) use (&$errors): bool {
            $errors[] = $message;

            return true;
        });
        try {
            $methods = $class->getMethods();
        } finally {
            restore_error_handler();
        }

        $this->assertSame([], $errors);
        $names = array_map(static fn (\ReflectionMethod $method): string => $method->getName(), $methods);
        $this->assertSame(['ownMethod', 'inheritedMethod'], $names);
    }
}
