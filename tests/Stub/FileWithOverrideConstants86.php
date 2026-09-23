<?php
declare(strict_types=1);
/**
 * Parser Reflection API
 *
 * @copyright Copyright 2026, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 *
 * The #[\Override] attribute is allowed on class constants since PHP 8.6 only, therefore this file
 * can be parsed by any runtime, but it may only be included by a PHP 8.6+ one.
 */

namespace Go\ParserReflection\Stub;

/**
 * Attribute that is applied to the class constants together with the #[\Override] one
 */
#[\Attribute(\Attribute::TARGET_CLASS_CONSTANT | \Attribute::IS_REPEATABLE)]
final class ClassConstantMarker86
{
    public function __construct(public readonly string $tag = '', public readonly int $priority = 0)
    {
    }
}

interface BaseContractWithConstants86
{
    const string LEVEL = 'base';

    const string KIND = 'contract';
}

interface ExtendedContractWithConstants86 extends BaseContractWithConstants86
{
    #[\Override]
    const string LEVEL = 'extended';
}

abstract class AbstractClassWithConstants86
{
    const string TAG = 'abstract-tag';

    const int WEIGHT = 1;
}

final class ClassWithOverriddenConstants86 extends AbstractClassWithConstants86 implements BaseContractWithConstants86
{
    /**
     * Plain constant without any attribute at all
     */
    const string PLAIN = 'plain';

    /**
     * Simplest case: single #[\Override] attribute on the inherited class constant
     */
    #[\Override]
    const string TAG = 'class-tag';

    /**
     * #[\Override] combined with an userland attribute inside the very same attribute group
     */
    #[\Override, ClassConstantMarker86(tag: 'weight', priority: 5)]
    const int WEIGHT = 42;

    /**
     * #[\Override] for an interface constant, mixed with repeated userland attributes
     */
    #[\Override]
    #[ClassConstantMarker86('first')]
    #[ClassConstantMarker86('second', priority: 2)]
    const string KIND = 'class-kind';
}

enum EnumWithOverriddenConstants86: string implements BaseContractWithConstants86
{
    #[\Override]
    const string LEVEL = 'enum';

    #[\Override, ClassConstantMarker86(tag: 'enum-kind')]
    const string KIND = 'enum-kind';

    const string OWN = 'own';

    case First = 'first';

    case Second = 'second';
}
