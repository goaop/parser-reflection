Parser Reflection API Library
-----------------

Parser Reflection API library provides a set of classes that extend original internal Reflection classes, but powered by [PHP-Parser](https://github.com/nikic/PHP-Parser) library thus allowing to create a reflection instance without loading classes into the memory.

This library can be used for analysing the source code, automatic proxy creation and much more.

[![Build Status](https://secure.travis-ci.org/goaop/parser-reflection.png?branch=master)](https://travis-ci.org/goaop/parser-reflection)
[![Minimum PHP Version](http://img.shields.io/badge/php-%3E%3D%205.5-8892BF.svg)](https://php.net/)
[![License](https://img.shields.io/packagist/l/goaop/parser-reflection.svg)](https://packagist.org/packages/goaop/parser-reflection)

Installation
------------

Library can be installed with composer. Installation is quite easy:

```bash
$ composer require goaop/parser-reflection
```

Composer will install the library to your project's `vendor/goaop/parser-reflection` directory.

Initialization
------------

Next step is initialization of `ReflectionEngine` in your application: 

```php
use Go\ParserReflection\Locator\ComposerLocator;
use Go\ParserReflection\ReflectionEngine;

ReflectionEngine::init(new ComposerLocator());
```
As you can see, engine just requires a locator instance - object that will be responsible for resolving class names into the file names. Best way for that is to utilize composer's `\Composer\Autoload\ClassLoader::findFile()` method. To use composer information, just pass a `ComposerLocator` instance to the reflection engine.

After initialization, you can use reflection classes like traditional ones:

```php
$parsedClass = new \Go\ParserReflection\ReflectionClass(SomeClass::class);
var_dump($parsedClass->getMethods());
```

How it works?
------------

To understand how library works let's look at what happens during the call to the `new \Go\ParserReflection\ReflectionClass(SomeClass::class)`

 * `\Go\ParserReflection\ReflectionClass` asks reflection engine to give an AST node for the given class name
 * Reflection engine asks a locator to locate a filename for the given class
 * `ComposerLocator` instance asks the Composer to find a filename for the given class and returns this result back to the reflection engine
 * Reflection engine loads the content of file and passes it to the [PHP-Parser](https://github.com/nikic/PHP-Parser) for tokenization and processing
 * PHP-Parser returns an AST (Abstract Syntax Tree)
 * Reflection engine then analyse this AST to extract specific nodes an wrap them into corresponding reflection classes.
 
Compatibility
------------

All parser reflection classes extend PHP internal reflection classes, this means that you can use `\Go\ParserReflection\ReflectionClass` instance in any place that asks for `\ReflectionClass` instance. All reflection methods should be compatible with original ones, providing an  except methods that requires object manipulation, such as `invoke()`, `invokeArgs()`, `setAccessible()`, etc. These methods will trigger the autoloading of class and switching to the internal reflection. 
