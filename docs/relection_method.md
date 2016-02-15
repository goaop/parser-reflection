ReflectionMethod
==============

The `ReflectionMethod` class reports an information about a method. This class is available in the standard PHP, so for any questions, please look at documentation for [`ReflectionMethod`][0]

But be careful, that several methods require the method and class to be loaded into the memory, otherwise an exception will be thrown.

List of methods, that require method and a corresponding class to be loaded
---------

- ReflectionMethod::getClosure — Returns a dynamically created closure for the method
- ReflectionMethod::invoke — Invokes method
- ReflectionMethod::invokeArgs — Invokes method args
- ReflectionMethod::setAccessible — Set method accessibility
  
[0]: http://php.net/manual/en/class.reflectionmethod.php
