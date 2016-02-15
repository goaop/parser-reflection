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
  
[0]: http://php.net/manual/en/class.reflectionclass.php
