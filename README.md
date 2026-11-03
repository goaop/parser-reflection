Parser Reflection API Library
-----------------

## 🔍 Static Code Analysis Meets Reflection

**Parser Reflection API** brings the power of PHP's Reflection API to static code analysis. Built on top of [nikic/php-parser](https://github.com/nikic/PHP-Parser), this library lets you introspect classes, interfaces, traits, enums, methods, properties, functions and constants **without ever loading them into memory**.

### ✨ Key Features

🧠 **Pure AST-Based Reflection**

Forget autoloading. This library parses your PHP source files directly into an Abstract Syntax Tree (AST) and extracts reflection data from the syntax itself — no `include`, no `require`, no side effects. Analyze classes without bootstrapping your entire application. Perfect for static analyzers, code generators, documentation tools, and IDE plugins.

### Why Use It?

- 📊 **Source code analysis** — inspect structure without executing anything
- 🧪 **Safe introspection** — avoid triggering constructors or static initializers
- 🔌 **Drop-in compatible** — extends native `\ReflectionClass`, `\ReflectionMethod`, `\ReflectionEnum`, etc.
- 🆕 **Modern PHP support** — enums, attributes (incl. `IS_INSTANCEOF` filtering), first-class callables, PHP 8.4 property hooks and lazy objects, `const`-declared global constants

![GitHub Workflow Status](https://img.shields.io/github/actions/workflow/status/goaop/parser-reflection/phpunit.yml?branch=master)
![PHPStan Badge](https://img.shields.io/badge/PHPStan-level%2010-brightgreen.svg?style=flat&link=https%3A%2F%2Fphpstan.org%2Fuser-guide%2Frule-levels)
[![GitHub release](https://img.shields.io/github/release/goaop/parser-reflection.svg)](https://github.com/goaop/parser-reflection/releases/latest)
[![Total Downloads](https://img.shields.io/packagist/dt/goaop/parser-reflection.svg)](https://packagist.org/packages/goaop/parser-reflection)
[![Daily Downloads](https://img.shields.io/packagist/dd/goaop/parser-reflection.svg)](https://packagist.org/packages/goaop/parser-reflection)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D%208.5-8892BF.svg)](https://php.net/)
[![License](https://img.shields.io/packagist/l/goaop/parser-reflection.svg)](https://packagist.org/packages/goaop/parser-reflection)
[![Sponsor](https://img.shields.io/badge/Sponsor-❤️-lightgray?style=flat&logo=github)](https://github.com/sponsors/lisachenko)

Installation
------------

Library can be installed with Composer. Installation is quite easy:

```bash
$ composer require goaop/parser-reflection
```

Composer will install the library to your project's `vendor/goaop/parser-reflection` directory.

Usage
------------

### Initialization

Prior to the first use library can be optionally initialized. If you use Composer for installing packages and loading classes, 
then you shouldn't worry about initialization, library will be initialized automatically.

If project uses a custom autoloader then you should follow the next steps:

1. Create a new class that implements `\Go\ParserReflection\LocatorInterface`
2. Create an instance of that class and pass it to the `ReflectionEngine::init()` method for initial configuration

### Classes whose ancestors can not be located

To reflect a class, the engine has to locate its parent class, interfaces and traits. An ancestor may be impossible
to locate: generated at runtime and loaded by another autoloader, declared by `class_alias()`, or defined outside the
files known to the locator. By default (the *strict* policy) every method that needs such an ancestor throws a
`Go\ParserReflection\ClassNotFoundException`.

With the *partial* policy the missing ancestors are left out instead, and the reflection says that it is partial:

```php
use Go\ParserReflection\MissingAncestorPolicy;
use Go\ParserReflection\ReflectionEngine;

ReflectionEngine::setMissingAncestorPolicy(MissingAncestorPolicy::Partial);
// or ReflectionEngine::init($locator, null, MissingAncestorPolicy::Partial);

$class = new \Go\ParserReflection\ReflectionClass(SomeClass::class);
if ($class->isPartial()) {
    // Names of the parent class, interfaces and traits that can not be located,
    // including the ones missing for the available ancestors
    var_dump($class->getMissingClassNames());
}
// Own methods, properties, constants and attributes, and the members of the available ancestors
var_dump($class->getMethods());
```

What a partial reflection reports:

- everything declared by the class itself and by its available ancestors stays available;
- a missing parent class is reported by `getParentClass()` as `false`, missing interfaces and traits are left out of
  `getInterfaces()` and `getTraits()`, and the members inherited from them are left out of
  `getMethods()`, `getProperties()`, `getConstants()` and alike; a method whose prototype belongs to a missing
  ancestor reports no prototype;
- the names of the missing ancestors are known from the source: `getParentClassName()` returns the name of the parent
  class as written, located or not, `getInterfaceNames()` and `implementsInterface()` include the missing interfaces
  declared by the class and by its available ancestors, `getTraitNames()` includes the missing traits the class uses,
  and `isSubclassOf()` recognizes the ancestors declared by the class itself.

`isPartial()` and `getMissingClassNames()` never throw for a missing ancestor, whatever the policy, so they can also
be used to check a class before reflecting it with the strict policy. The class itself must still be located by the
locator: `new ReflectionClass()` for a missing class throws with any policy.

### Reflecting concrete classes/methods/properties without loading them

Just use `Go\ParserReflection` package reflection classes like traditional ones:

```php
$parsedClass = new \Go\ParserReflection\ReflectionClass(SomeClass::class);
var_dump($parsedClass->getMethods());

$parsedMethod = new \Go\ParserReflection\ReflectionMethod(SomeClass::class, 'someMethod');
echo (string)$parsedMethod;
```

Or you can use an additional classes [`ReflectionFile`][0] and [`ReflectionFileNamespace`][1] to analyse a raw PHP files:

```php
$parsedFile     = new \Go\ParserReflection\ReflectionFile('SomeClass.php');
$fileNameSpaces = $parsedFile->getFileNamespaces();
// We can iterate over namespaces in the file
foreach ($fileNameSpaces as $namespace) {
    $classes = $namespace->getClasses();
    // Iterate over the classes in the namespace
    foreach ($classes as $class) {
        echo "Found class: ", $class->getName(), PHP_EOL;
        // Now let's show all methods in the class
        foreach ($class->getMethods() as $method) {
            echo "Found class method: ", $class->getName(), '::', $method->getName(), PHP_EOL;
        }
        
        // ...all properties in the class
        foreach ($class->getProperties() as $property) {
            echo "Found class property: ", $class->getName(), '->', $property->getName(), PHP_EOL;
        }
    }
}
```

How it works?
------------

To understand how library works let's look at what happens during the call to the `new \Go\ParserReflection\ReflectionClass(SomeClass::class)`

```text
┌─────────────────────┐
│   ReflectionClass   │
└──────────┬──────────┘
           │ asks for AST node
           ▼
┌─────────────────────┐
│  ReflectionEngine   │
└──────────┬──────────┘
           │ locates file
           ▼
┌─────────────────────┐
│  ComposerLocator    │ ──► Composer autoloader
└──────────┬──────────┘
           │ parses file
           ▼
┌─────────────────────┐
│  nikic/php-parser   │ ──► Returns AST
└──────────┬──────────┘
           │ wraps nodes
           ▼
┌─────────────────────┐
│  Reflection Objects │
└─────────────────────┘
```

 * `\Go\ParserReflection\ReflectionClass` asks reflection engine to give an AST node for the given class name
 * Reflection engine asks a locator to locate a filename for the given class
 * `ComposerLocator` instance asks the Composer to find a filename for the given class and returns this result back to the reflection engine
 * Reflection engine loads the content of file and passes it to the [PHP-Parser](https://github.com/nikic/PHP-Parser) for tokenization and processing
 * PHP-Parser returns an AST (Abstract Syntax Tree)
 * Reflection engine then analyse this AST to extract specific nodes and wrap them into corresponding reflection classes.

Compatibility
------------

All parser reflection classes extend PHP internal reflection classes, this means that you can use `\Go\ParserReflection\ReflectionClass` instance in any place that asks for `\ReflectionClass` instance. All reflection methods should be compatible with original ones, except methods that require object manipulation, such as `invoke()`, `invokeArgs()`, `setAccessible()`, etc. These methods will trigger the process of class loading and switching to the internal reflection.

The only exception is `\Go\ParserReflection\ReflectionConstant`: the native `\ReflectionConstant` class is declared as `final`, so this class mirrors its public API instead of extending it.

[0]: docs/reflection_file.md
[1]: docs/reflection_file_namespace.md
