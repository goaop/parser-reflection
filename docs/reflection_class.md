ReflectionClass
==============

The `ReflectionClass` class reports an information about a class. This class is available in the standard PHP, so for any questions, please look at documentation for [`ReflectionClass`][0]

But be careful, that several methods require the class to be loaded into the memory, otherwise an exception will be thrown.

List of methods, that require class to be loaded
---------

- ReflectionClass::newInstance — Creates a new class instance from given arguments.
- ReflectionClass::newInstanceArgs — Creates a new class instance from given arguments.
- ReflectionClass::newInstanceWithoutConstructor — Creates a new class instance without invoking the constructor.
- ReflectionClass::setStaticPropertyValue — Sets static property value

Methods, that are specific for this library
---------

- ReflectionClass::getNode — Returns the AST node of the class.
- ReflectionClass::isPartial — Checks whether some ancestors (parent class, interfaces or traits) of the class can not be located.
- ReflectionClass::getMissingClassNames — Returns the names of the ancestors that can not be located, including the ones missing for the available ancestors.
- ReflectionClass::getParentClassName — Returns the name of the parent class as written in the source, also when the parent class can not be located.

By default, reflecting an information that needs an ancestor that can not be located throws a
`Go\ParserReflection\ClassNotFoundException`. With `ReflectionEngine::setMissingAncestorPolicy(MissingAncestorPolicy::Partial)`
the missing ancestors are left out of the reflection instead, see the [README](../README.md) for the details.

[0]: http://php.net/manual/en/class.reflectionclass.php
