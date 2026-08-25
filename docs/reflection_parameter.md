ReflectionParameter
==============

The `ReflectionParameter` class reports an information about an parameter. This class is available in the standard PHP, so for any questions, please look at documentation for [`ReflectionParameter`][0]

Parameter doc comments
---------

PHP 8.6 allows doc comments on parameters and exposes them via `ReflectionParameter::getDocComment(): string|false`.
This method is implemented statically from the AST, so it is available on every supported PHP version, not only on 8.6:

```php
function store(
    /** @param Book[] $books */
    array $books,
): void {}
```

The engine returns the last doc comment that belongs to the parameter declaration in source order, which matches the
native behaviour for doc comments placed before the parameter, before or after its attributes, before its type and
inside its default value expression.

Known limitation: a doc comment written *after* the parameter it documents (for example `string $a /** doc */,`)
is reported by native reflection as belonging to that parameter, but PHP-Parser attaches every comment to the node
that follows it, so such a trailing comment never reaches the parameter node and `getDocComment()` returns `false`.

[0]: http://php.net/manual/en/class.reflectionparameter.php
