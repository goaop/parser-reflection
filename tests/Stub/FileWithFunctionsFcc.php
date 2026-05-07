<?php
declare(strict_types=1);
/**
 * Parser Reflection API
 *
 * @copyright Copyright 2015, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * Stub file containing functions/classes that use first-class callable syntax (FCC) as
 * default parameter values.
 *
 * NOTE: PHP runtime rejects FCC in constant-expression positions (parameter defaults /
 * property defaults), so this file must be reflected via AST only and must NOT be
 * loaded with require/include.  It is intentionally excluded from AbstractTestCase::getFilesToAnalyze().
 */

namespace Go\ParserReflection\Stub;

/**
 * Function whose parameter has an internal built-in FCC as default value.
 * Equivalent to what a proxy generator might emit.
 */
function functionWithBuiltinFccDefault($callable = \strlen(...))
{
}

/**
 * Function whose parameter has a user-defined static method FCC as default value.
 */
function functionWithStaticMethodFccDefault($callable = \Go\ParserReflection\ReflectionEngine::locateClassFile(...))
{
}

/**
 * Class with a method whose parameter carries a built-in FCC default.
 */
class ClassWithFccParameterDefault
{
    public function methodWithFccDefault($callable = \strlen(...))
    {
    }
}
