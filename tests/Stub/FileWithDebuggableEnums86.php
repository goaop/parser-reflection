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
 * The __debugInfo() magic method is allowed on enums since PHP 8.6 only (Debuggable Enums RFC),
 * therefore this file can be parsed by any runtime, but it may only be included by a PHP 8.6+ one.
 */

namespace Go\ParserReflection\Stub;

interface DebuggableEnumContract86
{
    public function label(): string;
}

enum PureDebuggableEnum86 implements DebuggableEnumContract86
{
    case Alpha;

    case Beta;

    public function label(): string
    {
        return strtolower($this->name);
    }

    /**
     * Magic method that is allowed for enums since PHP 8.6
     *
     * @return array<string, string>
     */
    public function __debugInfo(): array
    {
        return ['label' => $this->label()];
    }
}

enum BackedDebuggableEnum86: int implements DebuggableEnumContract86
{
    case Low = 1;

    case High = 10;

    public function label(): string
    {
        return $this->name . ':' . $this->value;
    }

    /**
     * Magic method with an explicit visibility modifier and a static helper next to it
     *
     * @return array<string, int|string>
     */
    public function __debugInfo(): array
    {
        return ['name' => $this->name, 'value' => $this->value, 'label' => self::describe($this)];
    }

    public static function describe(self $case): string
    {
        return $case->label();
    }
}

enum PlainEnumWithoutDebugInfo86: string
{
    case Only = 'only';
}
