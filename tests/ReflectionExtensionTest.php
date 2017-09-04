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
     * Performs method-by-method comparison with original reflection
     *
     * @dataProvider caseProvider
     *
     * @param ReflectionClass   $parsedClass Parsed class
     * @param \ReflectionMethod $refMethod Method to analyze
     * @param string                  $getterName Name of the reflection method to test
     */
    public function testReflectionMethodParity(
        ReflectionClass $parsedClass,
        $getterName
    ) {
        $className = $parsedClass->getName();
        $refClass  = new \ReflectionClass($className);

        $expectedValue = $refClass->$getterName();
        $actualValue   = $parsedClass->$getterName();
        $this->assertReflectorValueSame(
            $expectedValue,
            $actualValue,
            get_class($parsedClass) . "->$getterName() for method $className->$methodName() should be equal\nexpected: " . $this->getStringificationOf($expectedValue) . "\nactual: " . $this->getStringificationOf($actualValue)
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
