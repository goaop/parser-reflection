<?php

declare(strict_types=1);
/**
 * Parser Reflection API
 *
 * @copyright Copyright 2015, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\ParserReflection\Stub\MemberAttributeConstants\Traits {

    use Go\ParserReflection\Stub\MemberAttributeConstants\Flag;

    const LOCAL_FLAG = 'trait-namespace';

    trait FlaggedTrait
    {
        #[Flag(LOCAL_FLAG)]
        public function fromTrait(): void {}

        #[Flag(__NAMESPACE__)]
        public function namespaceFromTrait(): void {}
    }
}

namespace Go\ParserReflection\Stub\MemberAttributeConstants {

    use Go\ParserReflection\Stub\MemberAttributeConstants\Traits\FlaggedTrait;

    const LOCAL_FLAG = 'class-namespace';

    #[\Attribute(\Attribute::TARGET_ALL | \Attribute::IS_REPEATABLE)]
    final class Flag
    {
        public function __construct(public mixed $value = null) {}
    }

    #[Flag(true)]
    final class Subject
    {
        use FlaggedTrait;

        #[Flag(LOCAL_FLAG)]
        public const FLAGGED_CONSTANT = 1;

        #[Flag(PHP_INT_MAX)]
        public int $flaggedProperty = 0;

        #[Flag(true)]
        public function withTrue(): void {}

        #[Flag(false)]
        public function withFalse(): void {}

        #[Flag(null)]
        public function withNull(): void {}

        #[Flag(TRUE)]
        public function withUppercaseTrue(): void {}

        #[Flag(PHP_INT_MAX)]
        public function withGlobalConstant(): void {}

        #[Flag(\true)]
        public function withQualifiedTrue(): void {}

        #[Flag(self::class)]
        public function withClassConstant(): void {}

        #[Flag(LOCAL_FLAG)]
        public function withNamespacedConstant(): void {}

        #[Flag(__NAMESPACE__)]
        public function withNamespaceMagicConstant(): void {}

        #[Flag([LOCAL_FLAG, PHP_INT_MAX, null])]
        public function withConstantsInArray(): void {}

        public function withFlaggedParameter(#[Flag(LOCAL_FLAG)] int $value = PHP_INT_MAX): void {}
    }

    #[Flag(LOCAL_FLAG)]
    function flaggedFunction(#[Flag(null)] int $value = 0): void {}
}
