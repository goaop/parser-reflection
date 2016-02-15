ReflectionFileNamespace
==============

The `ReflectionFileNamespace` class reports an information about a namespace in the single file. This class is not available in the standard PHP

API
---

```php
class ReflectionFileNamespace
{
    public function __construct($fileName, $namespaceName, Namespace_ $namespaceNode = null) {}
    public function getClass($className) {}
    public function getClasses() {}
    public function getConstant($constantName) {}
    public function getConstants() {}
    public function getDocComment() {}
    public function getEndLine() {}
    public function getFileName() {}
    public function getFunction($functionName) {}
    public function getFunctions() {}
    public function getName() {}
    public function getNamespaceAliases() {}
    public function getStartLine() {}
    public function hasClass($className) {}
    public function hasConstant($constantName) {}
    public function hasFunction($functionName) {}
}
```

Methods
-------

- `ReflectionFileNamespace::__construct($fileName, $namespaceName, Namespace_ $namespaceNode = null)`

  Constructs an instance of `ReflectionFileNamespace` object for given `fileName` and `namespaceName`. Can accept custom `Namespace_` node as optional parameter.
 
- `ReflectionFileNamespace::getClass($className)`
  
  Returns the concrete [`ReflectionClass`][0] from the file namespace or `false` if there is no such a class in the current namespace
  
- `ReflectionFileNamespace::getClasses()`

  Returns an array with available classes in the namespace. Each class/trait/interface definition will be represented as a single instance of [`ReflectionClass`][0] in this list.

- `ReflectionFileNamespace::getConstant($constantName)`
  
  Returns a value for the constant with name `$constantName` in the file namespace or `false` if there is no such a constant in the current namespace.
  
- `ReflectionFileNamespace::getConstants()`

  Returns an array with all available constants in the namespace.

- `ReflectionFileNamespace::getDocComment()`

  Returns a doc-block section for file namespace if present, otherwise `false`  
  
- `ReflectionFileNamespace::getEndLine()`

  Returns a end line for the namespace. Be careful, this value is not correct for simple `namespace Name;` definitions.  
  
- `ReflectionFileNamespace::getFileName()`

  Returns a string with the name of file

- `ReflectionFileNamespace::getFunction($functionName)`
  
  Returns the concrete [`ReflectionFunction`][1] from the file namespace or `false` if there is no such a function in the current namespace
  
- `ReflectionFileNamespace::getFunctions()`

  Returns an array with available functions in the namespace. Each function definition will be represented as a single instance of [`ReflectionFunction`][1] in this list.
  
- `ReflectionFile::getName()`

  Returns a string with the name of current file namespace
  
- `ReflectionFileNamespace::getNamespaceAliases()`

  Returns an array with all imported class namespaces and aliases for them in the current namespace.
    
- `ReflectionFileNamespace::getStartLine()`

  Returns a start line for the namespace.
      
- `ReflectionFileNamespace::hasClass($className)`

  Checks if the requested class is present in the file namespace or not. Returns `true` if present.

- `ReflectionFileNamespace::hasConstant($constantName)`

  Checks if the requested constant is present in the file namespace or not. Returns `true` if present.
  
- `ReflectionFileNamespace::hasFunction($functionName)`

  Checks if the requested function is present in the file namespace or not. Returns `true` if present.

[0]: reflection_class.md
[1]: reflection_function.md
