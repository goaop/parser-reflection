<?php
/**
 * This is test file with global namespace
 */
declare(strict_types=1);

/**
 * @internal
 */
function __test()
{
}

define('INT_CONST', 5);
define('STRING_CONST', 'text');
define('BOOLEAN_CONST', true);

// Function call that won't match a constant definition.
test_func_call('AA', 'bb');

define('EXPRESSION_CONST', 5 > 7);
define('FUNCTION_CONST', mktime(hour: 12, minute: 33, second: 00));

// Conditionally defined constant won't be detected.
if (true) {
    define('TRUE_CONST', 1);
}

// Constants defined with dynamic names are partially supported
define(str_repeat('A', times: 10), true);

// Ignore function calls with dynamic function names.
$define('TEST', 5);
