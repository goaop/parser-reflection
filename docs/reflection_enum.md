ReflectionEnum
==============

The `ReflectionEnum` class reports an information about an enum. This class is available in the standard PHP, so for any questions, please look at documentation for [`ReflectionEnum`][0]

Enum cases are represented by the `ReflectionEnumUnitCase` and `ReflectionEnumBackedCase` classes, which extend their native counterparts [`ReflectionEnumUnitCase`][1] and [`ReflectionEnumBackedCase`][2].

Enum-specific API
---------

```php
final class ReflectionEnum extends \ReflectionEnum
{
    public function getBackingType(): ?ReflectionNamedType {}
    public function getCase(string $name): ReflectionEnumUnitCase|ReflectionEnumBackedCase {}
    public function getCases(): array {}
    public function hasCase(string $name): bool {}
    public function isBacked(): bool {}
}
```

But be careful, that several methods require the enum to be loaded into the memory, otherwise an exception will be thrown.

List of methods, that require enum to be loaded
---------

- ReflectionEnumUnitCase::getValue / ReflectionEnumBackedCase::getValue — Returns the concrete enum case object

[0]: https://php.net/manual/en/class.reflectionenum.php
[1]: https://php.net/manual/en/class.reflectionenumunitcase.php
[2]: https://php.net/manual/en/class.reflectionenumbackedcase.php
