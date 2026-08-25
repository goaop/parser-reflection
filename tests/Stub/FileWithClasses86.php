<?php
/**
 * Parser Reflection API
 *
 * @copyright Copyright 2026, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */
declare(strict_types=1);

namespace Go\ParserReflection\Stub;

/**
 * Baseline stub for the PHP 8.6 test scaffolding.
 *
 * It intentionally contains only syntax that is already valid and parseable, so that the
 * "FileWith*86.php" naming convention and the "PHP_VERSION_ID >= 80600" guard used by
 * {@see \Go\ParserReflection\AbstractTestCase::getFilesToAnalyze()} are proven by real tests
 * before the actual PHP 8.6 features land in their own per-feature stubs.
 *
 * Per-feature stubs that require the 8.6 runtime (partial function application, readonly
 * property defaults, "#[\Override]" on class constants, ...) must be added as separate
 * "FileWith<Feature>86.php" files, and every assertion that needs the 8.6 runtime has to be
 * guarded with "PHP_VERSION_ID >= 80600" so that the suite stays green on PHP 8.5.
 *
 * @see https://www.php.net/manual/en/migration86.php
 */

const PHP86_STUB_VERSION = '8.6';

interface InterfaceWithPhp86Contract
{
    public const string CONTRACT_NAME = 'php86';

    public function describe(): string;
}

trait TraitWithPhp86Helpers
{
    public function helperName(): string
    {
        return static::class;
    }
}

abstract class AbstractClassWithPhp86Members implements InterfaceWithPhp86Contract
{
    use TraitWithPhp86Helpers;

    public const int DEFAULT_PRIORITY = 10;

    protected function priority(): int
    {
        return self::DEFAULT_PRIORITY;
    }
}

final class ClassWithPhp86Members extends AbstractClassWithPhp86Members
{
    public const string LABEL = 'php86-class';

    public static int $counter = 0;

    private readonly string $identifier;

    public function __construct(
        string $identifier = 'default',
        public array $options = ['enabled' => true],
    ) {
        $this->identifier = $identifier;
    }

    public function describe(): string
    {
        return self::LABEL . ':' . $this->identifier;
    }

    public function combine(int|float $first, ?string $second = null, int ...$rest): string
    {
        return $first . ($second ?? '') . array_sum($rest);
    }

    public static function create(string $identifier = 'created'): static
    {
        return new static($identifier);
    }
}

enum EnumWithPhp86Cases: string implements InterfaceWithPhp86Contract
{
    case First  = 'first';
    case Second = 'second';

    public const string CONTRACT_NAME = 'php86-enum';

    public function describe(): string
    {
        return self::CONTRACT_NAME . ':' . $this->value;
    }
}

function functionWithPhp86Signature(
    InterfaceWithPhp86Contract $contract,
    string $prefix = PHP86_STUB_VERSION,
): string {
    return $prefix . '/' . $contract->describe();
}
