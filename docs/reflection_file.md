ReflectionFile
==============

The `ReflectionFile` class reports an information about a file with valid PHP source code. This class is not available in the standard PHP

API
---

```php
class ReflectionFile
{
    public function __construct($fileName, $topLevelNodes = null) {}
    public function getFileNamespace($namespaceName) {}
    public function getFileNamespaces() {}
    public function getName() {}
    public function hasFileNamespace($namespaceName) {}
    public function isStrictMode() {}
}
```

Methods
-------

- `ReflectionFile::__construct($fileName, $topLevelNodes = null)`

  Constructs an instance of `ReflectionFile` object for given `fileName`. Can accept custom AST-tree nodes as optional parameter.
 
- `ReflectionFile::getFileNamespace($namespaceName)`
  
  Returns a [`ReflectionFileNamespace`][0] instance for the specified namespace in a file. If you don't know an exact name of namespace in the file, then use `getFileNamespaces()` method to get a full list of namespaces for inspection.
  
- `ReflectionFile::getFileNamespaces()`

  Returns an array with available namespaces in the file. Each `namespace {}` section will be represented as a single instance of [`ReflectionFileNamespace`][0] in this list.
  
- `ReflectionFile::getName()`

  Returns a string with the name of file
  
- `ReflectionFile::hasFileNamespace($namespaceName)`

  Checks if requested namespace is present in the file or not. Returns `true` if present.
  
- `ReflectionFile::isStrictMode()`

  Checks if current file has enabled strict mode via `declare(strict_types=1)` for PHP>=7.0
[0]: reflection_file_namespace.md
