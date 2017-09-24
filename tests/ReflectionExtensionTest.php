<?php
namespace Go\ParserReflection;

class ReflectionExtensionTest extends TestCaseBase
{
    /**
     * Name of the class to compare
     *
     * @var string
     */
    protected static $reflectionClassToTest = \ReflectionExtension::class;

    /**
     * Verifies that function reflections produce proper extension reflections.
     *
     * @dataProvider queryFunctionCaseProvider
     *
     * @param string $extensionFunctionName Name of the function being reflected.
     * @param string $extensionName         Name of the ReflectionExtension it should produce.
     */
    public function testRefFuncGetExt(
        $extensionFunctionName,
        $extensionName
    ) {
        // If this wasn't based of a method argument, it would have been better in an @require.
        if (!extension_loaded($extensionName)) {
            $this->markTestSkipped(sprintf('Extension %s is required.', $extensionName));
        }
        $this->verifyProperExtensionQuery(
            new \ReflectionFunction($extensionFunctionName),
            new \Go\ParserReflection\ReflectionFunction($extensionFunctionName),
            "function {$extensionFunctionName}()"
        );
    }

    /**
     * Verifies that class reflections produce proper extension reflections.
     *
     * @dataProvider queryClassCaseProvider
     *
     * @param string $extensionClassName Name of the class being reflected.
     * @param string $extensionName      Name of the ReflectionExtension it should produce.
     */
    public function testRefClassGetExt(
        $extensionClassName,
        $extensionName
    ) {
        // If this wasn't based of a method argument, it would have been better in an @require.
        if (!extension_loaded($extensionName)) {
            $this->markTestSkipped(sprintf('Extension %s is required.', $extensionName));
        }
        $this->verifyProperExtensionQuery(
            new \ReflectionClass($extensionClassName),
            new \Go\ParserReflection\ReflectionClass($extensionClassName),
            "class {$extensionClassName}"
        );
    }

    /**
     * Verifies that method reflections produce proper extension reflections.
     *
     * @dataProvider queryMethodCaseProvider
     *
     * @param string $extensionClassName Name of the class being reflected.
     * @param string $methodName         Name of the class's method.
     * @param string $extensionName      Name of the ReflectionExtension it should produce.
     */
    public function testRefMethodGetExt(
        $extensionClassName,
        $methodName,
        $extensionName
    ) {
        // If this wasn't based of a method argument, it would have been better in an @require.
        if (!extension_loaded($extensionName)) {
            $this->markTestSkipped(sprintf('Extension %s is required.', $extensionName));
        }
        $nativeClassRef = new \ReflectionClass($extensionClassName);
        $parsedClassRef = new \Go\ParserReflection\ReflectionClass($extensionClassName);
        $this->verifyProperExtensionQuery(
            $nativeClassRef->getMethod($methodName),
            $parsedClassRef->getMethod($methodName),
            "method {$extensionClassName}::{$methodName}()"
        );
    }

    /**
     * Verifies output of getExtension() methods.
     *
     * @param \Reflection $nativeQueryTarget Native reflector to query.
     * @param IReflection $parsedQueryTarget Parsed reflection to compare.
     * @param string      $targetDescription description of what's being reflected.
     */
    public function verifyProperExtensionQuery(
        $nativeQueryTarget,
        $parsedQueryTarget,
        $targetDescription
    ) {
        $nativeExtensionRef   = $nativeQueryTarget->getExtension();
        $parsedExtensionRef   = $parsedQueryTarget->getExtension();
        $this->assertEquals(
            static::$reflectionClassToTest,
            get_class($nativeExtensionRef),
            'Expected class');
        $this->assertEquals(
            'Go\\ParserReflection\\' . static::$reflectionClassToTest,
            get_class($parsedExtensionRef),
            'Expected class');
        $this->assertReflectorValueSame(
            $nativeExtensionRef,
            $parsedExtensionRef,
            get_class($parsedQueryTarget) . "->getExtension() for {$targetDescription} should be equal\nexpected: " . $this->getStringificationOf($nativeExtensionRef) . "\nactual: " . $this->getStringificationOf($parsedExtensionRef)
        );
    }

    /**
     * Provides full test-case list in the form [ParsedClass, ReflectionMethod, getter name to check]
     *
     * @return array
     */
    public function funcCaseProvider()
    {
        $allNameGetters = $this->getGettersToCheck();

        $testCases = [];
        $classes   = $this->getFunctionsToAnalyze();
        foreach ($classes as $testCaseDesc => $classFilePair) {
            if ($classFilePair['fileName']) {
                $fileNode       = ReflectionEngine::parseFile($classFilePair['fileName']);
                $reflectionFile = new ReflectionFile($classFilePair['fileName'], $fileNode);
                $namespace      = $this->getNamespaceFromName($classFilePair['class']);
                $fileNamespace  = $reflectionFile->getFileNamespace($namespace);
                $parsedClass    = $fileNamespace->getClass($classFilePair['class']);
                include_once $classFilePair['fileName'];
            } else {
                $parsedClass    = new ReflectionClass($classFilePair['class']);
            }
            foreach ($allNameGetters as $getterName) {
                $testCases[$testCaseDesc . ', ' . $getterName] = [
                    $parsedClass,
                    $getterName
                ];
            }
        }

        return $testCases;
    }

    /**
     * Provides full test-case list in the form [extensionFunctionName, extensionName]
     *
     * @return array
     */
    public function queryFunctionCaseProvider()
    {
        $extInfos = $this->getExtensionInfo();

        $testCases = [];
        foreach ($extInfos as $extName => $extInfo) {
            if (array_key_exists('functions', $extInfo) && is_array($extInfo['functions'])) {
                foreach ($extInfo['functions'] as $funcName) {
                    $testCases[$extName . ', ' . $funcName] = [
                        '$extensionFunctionName' => $funcName,
                        '$extensionName'         => $extName,
                    ];
                }
            }
        }

        return $testCases;
    }

    /**
     * Provides full test-case list in the form [extensionClassName, extensionName]
     *
     * @return array
     */
    public function queryClassCaseProvider()
    {
        $extInfos = $this->getExtensionInfo();

        $testCases = [];
        foreach ($extInfos as $extName => $extInfo) {
            if (array_key_exists('classes', $extInfo) && is_array($extInfo['classes'])) {
                foreach ($extInfo['classes'] as $className) {
                    $testCases[$extName . ', ' . $className] = [
                        '$extensionClassName' => $className,
                        '$extensionName'      => $extName,
                    ];
                }
            }
        }

        return $testCases;
    }

    /**
     * Provides full test-case list in the form [extensionClassName, methodName, extensionName]
     *
     * @return array
     */
    public function queryMethodCaseProvider()
    {
        $extInfos = $this->getExtensionInfo();

        $testCases = [];
        foreach ($extInfos as $extName => $extInfo) {
            if (array_key_exists('classes', $extInfo) && is_array($extInfo['classes'])) {
                foreach ($extInfo['classes'] as $className) {
                    $classRef = new \ReflectionClass($className);
                    foreach ($classRef->getMethods() as $methodRef) {
                        $testCases[$extName . ', ' . $className] = [
                            '$extensionClassName' => $className,
                            '$methodName'         => $methodRef->getName(),
                            '$extensionName'      => $extName,
                        ];
                    }
                }
            }
        }

        return $testCases;
    }

    /**
     * Returns list of ReflectionMethod getters that be checked directly without additional arguments
     *
     * @return array
     */
    protected function getGettersToCheck()
    {
        $allNameGetters = [
            'getClasses', 'getClassNames', 'getConstants', 'getDependencies',
            'getFunctions', 'getINIEntries', 'getName', 'getVersion', 'info',
            'isPersistent', 'isTemporary', '__toString'
        ];

        return $allNameGetters;
    }

    /**
     * Returns list of functions and classes defined by common extensions
     *
     * @return array
     */
    protected function getExtensionInfo()
    {
        $extensionInfos = [
            'date' => [
                'name' => 'date',
                'classes' => [
                    // None of these classes were added after PHP 5.5.
                    'DateTime',
                    'DateTimeImmutable',
                    'DateTimeInterface',
                    'DateTimeZone',
                    'DateInterval',
                    'DatePeriod',
                ],
                'constants' => [
                    // None of these constants were added after PHP 5.5.
                    'SUNFUNCS_RET_TIMESTAMP',
                    'SUNFUNCS_RET_STRING',
                    'SUNFUNCS_RET_DOUBLE',
                ],
                'functions' => [
                    // None of these functions were added after PHP 5.5.
                    'checkdate', 'date_add', 'date_create_from_format',
                    'date_create_immutable_from_format', 'date_create_immutable',
                    'date_create', 'date_date_set', 'date_default_timezone_get',
                    'date_default_timezone_set', 'date_diff', 'date_format',
                    'date_get_last_errors', 'date_interval_create_from_date_string',
                    'date_interval_format', 'date_isodate_set', 'date_modify',
                    'date_offset_get', 'date_parse_from_format', 'date_parse',
                    'date_sub', 'date_sun_info', 'date_sunrise', 'date_sunset',
                    'date_time_set', 'date_timestamp_get', 'date_timestamp_set',
                    'date_timezone_get', 'date_timezone_set', 'date', 'getdate',
                    'gettimeofday', 'gmdate', 'gmmktime', 'gmstrftime', 'idate',
                    'localtime', 'microtime', 'mktime', 'strftime', 'strptime',
                    'strtotime', 'time', 'timezone_abbreviations_list',
                    'timezone_identifiers_list', 'timezone_location_get',
                    'timezone_name_from_abbr', 'timezone_name_get',
                    'timezone_offset_get', 'timezone_open',
                    'timezone_transitions_get', 'timezone_version_get'
                ],
            ],
            'json' => [
                'name' => 'json',
                'classes' => [
                    // None of these classes were added after PHP 5.5.
                    'JsonSerializable',
                ],
                'constants' => [
                    // None of these constants were added after PHP 5.5.
                    'JSON_ERROR_NONE', 'JSON_ERROR_DEPTH', 'JSON_ERROR_STATE_MISMATCH',
                    'JSON_ERROR_CTRL_CHAR', 'JSON_ERROR_SYNTAX', 'JSON_ERROR_UTF8',
                    'JSON_ERROR_RECURSION', 'JSON_ERROR_INF_OR_NAN',
                    'JSON_ERROR_UNSUPPORTED_TYPE', 'JSON_PARTIAL_OUTPUT_ON_ERROR',
                    'JSON_BIGINT_AS_STRING', 'JSON_OBJECT_AS_ARRAY', 'JSON_HEX_TAG',
                    'JSON_HEX_AMP', 'JSON_HEX_APOS', 'JSON_HEX_QUOT', 'JSON_FORCE_OBJECT',
                    'JSON_NUMERIC_CHECK', 'JSON_PRETTY_PRINT', 'JSON_UNESCAPED_SLASHES',
                    'JSON_UNESCAPED_UNICODE', 'JSON_PARTIAL_OUTPUT_ON_ERROR'
                ],
                'functions' => [
                    // None of these functions were added after PHP 5.5.
                    'json_decode', 'json_encode', 'json_last_error_msg', 'json_last_error'
                ],
            ]
        ];
        // These constants were added later
        if (PHP_VERSION_ID >= 50606) {
            $extensionInfos['json']['constants'][] = 'JSON_PRESERVE_ZERO_FRACTION';
        }
        if (PHP_VERSION_ID >= 70000) {
            $extensionInfos['json']['constants'][] = 'JSON_ERROR_INVALID_PROPERTY_NAME';
            $extensionInfos['json']['constants'][] = 'JSON_ERROR_UTF16';
        }
        if (PHP_VERSION_ID >= 70100) {
            $extensionInfos['json']['constants'][] = 'JSON_UNESCAPED_LINE_TERMINATORS';
        }

        return $extensionInfos;
    }
}
