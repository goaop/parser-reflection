ReflectionFunction
==============

The `ReflectionFunction` class reports an information about a function. This class is available in the standard PHP, so for any questions, please look at documentation for [`ReflectionFunction`][0]

But be careful, that several methods require the function to be loaded into the memory, otherwise an exception will be thrown.

List of methods, that require function to be loaded
---------

- ReflectionFunction::getClosure — Returns a dynamically created closure for the function
- ReflectionFunction::invoke — Invokes function
- ReflectionFunction::invokeArgs — Invokes function args
  
[0]: http://php.net/manual/en/class.reflectionfunction.php
