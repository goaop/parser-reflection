<?php
/**
 * Parser Reflection API
 *
 * @copyright Copyright 2025, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */
declare(strict_types=1);

namespace Go\ParserReflection\Stub;

/**
 * @see https://wiki.php.net/rfc/property-hooks
 */

class ClassWithPhp84PropertyHooks
{
    private string $backing = 'default';

    public string $name {
        get => $this->backing;
        set => $this->backing = strtoupper($value);
    }
}

/* Not supported yet
interface InterfaceWithPhp84AbstractProperty
{
    public string $name { get; }
}
*/

/**
 * https://wiki.php.net/rfc/asymmetric-visibility-v2
 */
class ClassWithPhp84AsymmetricVisibility
{
    // These create a public-read, protected-write, write-once property.
    public protected(set) readonly string $explicitPublicWriteOnceProtectedProperty;
    public readonly string $implicitPublicReadonlyWriteOnceProperty;
    readonly string $implicitReadonlyWriteOnceProperty;

    // These creates a public-read, private-set, write-once, final property.
    public private(set) readonly string $explicitPublicWriteOncePrivateProperty;
    private(set) readonly string $implicitPublicReadonlyWriteOncePrivateProperty;

    // These create a public-read, public-write, write-once property.
    // While use cases for this configuration are likely few,
    // there's no intrinsic reason it should be forbidden.
    public public(set) readonly string $explicitPublicWriteOncePublicProperty;
    public(set) readonly string $implicitPublicReadonlyWriteOncePublicProperty;

    // These create a private-read, private-write, write-once, final property.
    private private(set) readonly string $explicitPrivateWriteOncePrivateProperty;
    private readonly string $implicitPrivateReadonlyWriteOncePrivateProperty;

    // These create a protected-read, protected-write, write-once property.
    protected protected(set) readonly string $explicitProtectedWriteOnceProtectedProperty;
    protected readonly string $implicitProtectedReadonlyWriteOnceProtectedProperty;

    public function __construct(
        private(set) string $promotedPrivateSetStringProperty,
        protected(set) string $promotedProtectedSetStringProperty,
        protected private(set) int $promotedProtectedPrivateSetIntProperty,
    ) {}

}
