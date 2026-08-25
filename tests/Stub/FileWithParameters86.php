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

namespace Go\ParserReflection\Stub;

use Attribute;

/**
 * Doc comments on parameters are a PHP 8.6 feature, but syntactically they are ordinary comments,
 * therefore this file is parsable (and includable) on every supported PHP version.
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
class ParameterMarker86
{
    public function __construct(public readonly string $note = '')
    {
    }
}

/**
 * Doc comment of the function itself, it must never leak into the first parameter.
 */
function parametersWithDocComments86(
    /** @param string $documented simple leading doc comment */
    string $documented,
    int $undocumented,
    /* not a doc comment, only a regular block comment */
    string $blockCommented = 'default',
    // not a doc comment, only a line comment
    string $lineCommented = 'default',
    /** @param array<int> $variadic variadic doc comment */
    int ...$variadic
) {
}

function parametersWithReferencesAndAttributes86(
    /** @param array<string> $byReference by-reference doc comment */
    array &$byReference,
    /** doc comment placed before the attribute */
    #[ParameterMarker86('before')]
    string $docBeforeAttribute,
    #[ParameterMarker86('after')]
    /** doc comment placed after the attribute */
    string $docAfterAttribute,
    /** first doc comment that is superseded */
    /** last doc comment wins */
    string $lastDocCommentWins,
    /** doc comment followed by a regular comment */
    /* regular comment does not reset the doc comment */
    string $docCommentThenBlockComment
) {
}

function parametersWithDefaultsAndTypes86(
    /** @param string|null $nullableWithDefault nullable doc comment */
    ?string $nullableWithDefault = null,
    /** @param int|float $unionTyped union type doc comment */
    int|float $unionTyped = 0,
    /** @param array<mixed> $arrayDefault doc comment for array default */
    array $arrayDefault = [1, 2, 3]
) {
}

class ClassWithDocumentedParameters86
{
    public function __construct(
        /** @var string promoted property doc comment */
        public readonly string $promoted,
        /** @var int promoted protected property doc comment */
        protected int $promotedProtected = 0,
        private ?array $promotedUndocumented = null
    ) {
    }

    /**
     * Doc comment of the method itself, it must never leak into the first parameter.
     */
    public function documentedMethod(
        int $first,
        /** @param string $second method parameter doc comment */
        string $second
    ): void {
    }

    public static function documentedStaticMethod(
        /** @param self $instance static method parameter doc comment */
        self $instance
    ): void {
    }
}

/**
 * The doc comment below is placed *after* the parameter it documents and is a known parity gap:
 * PHP-Parser attaches such a trailing comment to the following node instead of to the parameter.
 *
 * @see \Go\ParserReflection\ReflectionParameterTest::testTrailingDocCommentIsAKnownParityGap()
 */
function parameterWithTrailingDocComment86(
    string $trailing /** trailing doc comment, attached to the parameter by the engine only */
) {
}
