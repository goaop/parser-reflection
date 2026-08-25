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
 * Full matrix of property declarations for the PHP 8.6 accessibility introspection API
 * (ReflectionProperty::isReadable() / ReflectionProperty::isWritable()).
 *
 * Only PHP 8.4/8.5 syntax is used, so this file parses and loads on every supported runtime.
 */

class AccessibilityRoot
{
}

class AccessibilityParent extends AccessibilityRoot
{
    public int $pub = 1;
    protected int $prot = 2;
    private int $priv = 3;

    public protected(set) int $pubProtSet = 4;
    public private(set) int $pubPrivSet = 5;
    protected private(set) int $protPrivSet = 6;

    public readonly int $ro;
    protected readonly int $roProt;
    private readonly int $roPriv;
    public private(set) readonly int $roPrivSet;
    public public(set) readonly int $roPubSet;

    public static int $statPub = 7;
    protected static int $statProt = 8;
    private static int $statPriv = 9;

    public int $virtGetOnly { get => 42; }
    public int $virtSetOnly { set { } }
    public int $virtBoth { get => 42; set { } }

    public int $backedGetHook { get => $this->backedGetHook + 1; }
    public int $backedSetHook { set => $value * 2; }
    public int $backedBothHooks = 1 {
        get => $this->backedBothHooks;
        set => $value;
    }

    protected int $protHooked { get => 10; }
    public protected(set) int $asymBackedHooked {
        get => $this->asymBackedHooked;
        set => $value;
    }

    public int $typedNoDefault;
    public $untyped;

    public function __construct(
        public readonly string $promotedRo = 'ro',
        private string $promotedPriv = 'priv',
        protected private(set) string $promotedProtPrivSet = 'pps',
    ) {
    }

    /**
     * Initializes all declared readonly properties from the declaring scope
     */
    public function initAllReadonly(): void
    {
        $this->ro        = 1;
        $this->roProt    = 2;
        $this->roPriv    = 3;
        $this->roPrivSet = 4;
        $this->roPubSet  = 5;
    }
}

class AccessibilityChild extends AccessibilityParent
{
    private int $shadowPriv = 10;
}

class AccessibilityGrandChild extends AccessibilityChild
{
}

class AccessibilityUnrelated
{
}

interface AccessibilityInterface
{
    public int $ifaceGetOnly { get; }
    public int $ifaceGetSet { get; set; }
}

abstract class AccessibilityAbstract
{
    abstract public int $absGetOnly { get; }
    abstract public int $absSetOnly { set; }
    abstract public int $absBoth { get; set; }

    public protected(set) int $absPlainAsym = 11;
}

final readonly class AccessibilityReadonlyClass
{
    public function __construct(public int $promotedInRoClass = 1)
    {
    }
}

enum AccessibilityEnum: string
{
    case One = 'one';
}
