ReflectionConstant
==============

The `ReflectionConstant` class reports an information about a global or namespaced constant declared with the `const` keyword. The native [`ReflectionConstant`][0] class is declared as `final`, therefore this class can not extend it and mirrors its public API instead.

Constants defined via `define(...)` are not supported, because they are created at runtime and can not be resolved statically.

API
---

```php
final class ReflectionConstant implements NodeAwareInterface, Reflector, Stringable
{
    public function __construct(string $constantName) {}
    public function getName(): string {}
    public function getShortName(): string {}
    public function getNamespaceName(): string {}
    public function getValue(): mixed {}
    public function isDeprecated(): bool {}
    public function getAttributes(?string $name = null, int $flags = 0): array {}
    public function getNode(): PhpParser\Node\Const_ {}
}
```

Methods
-------

- `ReflectionConstant::__construct(string $constantName)`

  Constructs an instance of `ReflectionConstant` for the given fully-qualified constant name.

- `ReflectionConstant::getName()`

  Returns the fully-qualified name of the constant.

- `ReflectionConstant::getShortName()`

  Returns the short name of the constant, without its namespace.

- `ReflectionConstant::getNamespaceName()`

  Returns the namespace the constant is declared in, or an empty string for the global namespace.

- `ReflectionConstant::getValue()`

  Returns the statically-resolved value of the constant.

- `ReflectionConstant::isDeprecated()`

  Checks if the constant is marked with the `#[\Deprecated]` attribute.

- `ReflectionConstant::getAttributes(?string $name = null, int $flags = 0)`

  Returns the list of attributes declared on the constant, with optional filtering by name (including `ReflectionAttribute::IS_INSTANCEOF` flag support).

- `ReflectionConstant::getNode()`

  Returns the underlying AST node with the `NAME = value` declaration.

[0]: https://php.net/manual/en/class.reflectionconstant.php
