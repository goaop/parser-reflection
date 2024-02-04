Parser Reflection API Library
-----------------
This library is **deprecated**. Please use [BetterReflection](https://github.com/Roave/BetterReflection).

Parser Reflection API library provides a set of classes that extend original internal Reflection classes, but powered by [PHP-Parser](https://github.com/nikic/PHP-Parser) library thus allowing to create a reflection instance without loading classes into the memory.

This library can be used for analysing the source code; for automatic proxy creation and much more.

[![Build Status](https://scrutinizer-ci.com/g/goaop/parser-reflection/badges/build.png?b=master)](https://scrutinizer-ci.com/g/goaop/parser-reflection/build-status/master)
[![Code Coverage](https://scrutinizer-ci.com/g/goaop/parser-reflection/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/goaop/parser-reflection/?branch=master)
[![Total Downloads](https://img.shields.io/packagist/dt/goaop/parser-reflection.svg)](https://packagist.org/packages/goaop/parser-reflection)
[![Daily Downloads](https://img.shields.io/packagist/dd/goaop/parser-reflection.svg)](https://packagist.org/packages/goaop/parser-reflection)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/goaop/parser-reflection/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/goaop/parser-reflection/?branch=master)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D%208.2-8892BF.svg)](https://php.net/)
[![License](https://img.shields.io/packagist/l/goaop/parser-reflection.svg)](https://packagist.org/packages/goaop/parser-reflection)

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

 * `\Go\ParserReflection\ReflectionClass` asks reflection engine to give an AST node for the given class name
 * Reflection engine asks a locator to locate a filename for the given class
 * `ComposerLocator` instance asks the Composer to find a filename for the given class and returns this result back to the reflection engine
 * Reflection engine loads the content of file and passes it to the [PHP-Parser](https://github.com/nikic/PHP-Parser) for tokenization and processing
 * PHP-Parser returns an AST (Abstract Syntax Tree)
 * Reflection engine then analyse this AST to extract specific nodes and wrap them into corresponding reflection classes.

Compatibility
------------

All parser reflection classes extend PHP internal reflection classes, this means that you can use `\Go\ParserReflection\ReflectionClass` instance in any place that asks for `\ReflectionClass` instance. All reflection methods should be compatible with original ones, providing an  except methods that requires object manipulation, such as `invoke()`, `invokeArgs()`, `setAccessible()`, etc. These methods will trigger the process of class loading and switching to the internal reflection.

[0]: docs/reflection_file.md
[1]: docs/reflection_file_namespace.md
