ReflectionProperty
==============

The `ReflectionProperty` class reports an information about a property. This class is available in the standard PHP, so for any questions, please look at documentation for [`ReflectionProperty`][0]

But be careful, that several methods require the property and class to be loaded into the memory, otherwise an exception will be thrown.

List of methods, that require property and a corresponding class to be loaded
---------

- ReflectionProperty::setAccessible — Set property accessibility
- ReflectionProperty::setValue — Set property value

  
[0]: http://php.net/manual/en/class.reflectionproperty.php
