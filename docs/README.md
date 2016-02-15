ParserReflection API Reference
==============

Introduction
--------
PHP comes with a complete reflection API that adds the ability to reverse-engineer classes, interfaces, functions, methods and extensions. Additionally, the reflection API offers ways to retrieve doc comments for functions, classes and methods.

But this reflection API requires concrete element to be loaded into the memory. Once element is loaded, it can not be changed, modified or updated without special extensions. 

`goaop\parser-reflection` packet is a userland implementation of reflection API that is fully compatible with internal one, but doesn't require element (e.g. class or function) to be loaded into the PHP. Only the source code is required for performing the raw analysis.

Reference
---------

- [`ReflectionClass`](reflection_class.md)
- [`ReflectionFile`](reflection_file.md)
- [`ReflectionFileNamespace`](reflection_file_namespace.md)
- [`ReflectionFunction`](reflection_function.md)
- [`ReflectionMethod`](reflection_method.md)
- [`ReflectionParameter`](reflection_parameter.md)
- [`ReflectionProperty`](reflection_property.md)
- [`ReflectionType`](reflection_type.md)
