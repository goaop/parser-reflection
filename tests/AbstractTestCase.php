<?php
/**
 * Parser Reflection API
 *
 * @copyright Copyright 2016, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */
namespace Go\ParserReflection;

use Go\ParserReflection\Stub\AbstractClassWithMethods;

abstract class AbstractTestCase extends \PHPUnit_Framework_TestCase
{
    /**
     * @var ReflectionFileNamespace
     */
    protected $parsedRefFileNamespace;

    /**
     * @var ReflectionClass
     */
    protected $parsedRefClass;

    /**
     * Name of the class to compare
     *
     * @var string
     */
    protected static $reflectionClassToTest = \Reflection::class;

    /**
     * Name of the class to load for default tests
     *
     * @var string
     */
    protected static $defaultClassToLoad = AbstractClassWithMethods::class;

    public function testCoverAllMethods()
    {
        $allInternalMethods = get_class_methods(static::$reflectionClassToTest);
        $allMissedMethods   = [];

        foreach ($allInternalMethods as $internalMethodName) {
            if ('export' === $internalMethodName) {
                continue;
            }
            $refMethod    = new \ReflectionMethod(__NAMESPACE__ . '\\' . static::$reflectionClassToTest, $internalMethodName);
            $definerClass = $refMethod->getDeclaringClass()->getName();
            if (strpos($definerClass, __NAMESPACE__) !== 0) {
                $allMissedMethods[] = $internalMethodName;
            }
        }

        if ($allMissedMethods) {
            $this->markTestIncomplete('Methods ' . join($allMissedMethods, ', ') . ' are not implemented');
        }
    }


    /**
     * Provides a list of files for analysis
     *
     * @return array
     */
    public function getFilesToAnalyze()
    {
        $files = ['PHP5.5' => [__DIR__ . '/Stub/FileWithClasses55.php']];

        if (PHP_VERSION_ID >= 50600) {
            $files['PHP5.6'] = [__DIR__ . '/Stub/FileWithClasses56.php'];
        }
        if (PHP_VERSION_ID >= 70000) {
            $files['PHP7.0'] = [__DIR__ . '/Stub/FileWithClasses70.php'];
        }
        if (PHP_VERSION_ID >= 70100) {
            $files['PHP7.1'] = [__DIR__ . '/Stub/FileWithClasses71.php'];
        }

        return $files;
    }

    /**
     * Provides a list of classes for analysis in the form [Class, FileName]
     *
     * @return array
     */
    public function getClassesToAnalyze()
    {
        // Random selection of built in classes.
        $builtInClasses = ['stdClass', 'DateTime', 'Exception', 'Directory', 'Closure', 'ReflectionFunction'];
        $classes = [];
        foreach ($builtInClasses as $className) {
            $classes[$className] = ['class' => $className, 'fileName'  => null];
        }
        $files = $this->getFilesToAnalyze();
        foreach ($files as $filenameArgList) {
            $argKeys = array_keys($filenameArgList);
            $fileName = $filenameArgList[$argKeys[0]];
            $resolvedFileName = stream_resolve_include_path($fileName);
            $fileNode = ReflectionEngine::parseFile($resolvedFileName);

            $reflectionFile = new ReflectionFile($resolvedFileName, $fileNode);
            foreach ($reflectionFile->getFileNamespaces() as $fileNamespace) {
                foreach ($fileNamespace->getClasses() as $parsedClass) {
                    $classes[$argKeys[0] . ': ' . $parsedClass->getName()] = [
                        'class'    => $parsedClass->getName(),
                        'fileName' => $resolvedFileName
                    ];
                }
            }
        }

        return $classes;
    }

    /**
     * Extracts namespace from class, function or constant name.
     *
     * @return string
     */
    protected function getNamespaceFromName($name)
    {
        $nameParts = explode('\\', $name);
        array_pop($nameParts);
        return implode('\\', $nameParts);
    }

    /**
     * Returns list of ReflectionMethod getters that be checked directly without additional arguments
     *
     * @return array
     */
    abstract protected function getGettersToCheck();

    /**
     * Setups file for parsing
     *
     * @param string $fileName File to use
     */
    protected function setUpFile($fileName)
    {
        $fileName = stream_resolve_include_path($fileName);
        $fileNode = ReflectionEngine::parseFile($fileName);

        $reflectionFile = new ReflectionFile($fileName, $fileNode);

        $parsedFileNamespace          = $reflectionFile->getFileNamespace('Go\ParserReflection\Stub');
        $this->parsedRefFileNamespace = $parsedFileNamespace;
        $this->parsedRefClass         = $parsedFileNamespace->getClass(static::$defaultClassToLoad);

        include_once $fileName;
    }

    protected function setUp()
    {
        $this->setUpFile(__DIR__ . '/Stub/FileWithClasses55.php');
    }

    protected function getStringificationOf($value, $maxLen = 128)
    {
        $strlen = function_exists('mb_strlen') ? 'mb_strlen' : 'strlen';
        $substr = function_exists('mb_substr') ? 'mb_substr' : 'substr';
        if (in_array(gettype($value), ['NULL', 'boolean', 'integer', 'double'])) {
            return var_export($value, true);
        }
        if (gettype($value) == 'string') {
            $result = var_export($value, true);
            if ($strlen($result) <= $maxLen) {
                return $result;
            }
            if ($maxLen <= 5) {
                return "'...'";
            }
            $cropCount = 3 + $strlen($result) - $maxLen;
            return var_export(
                $substr($value, 0, max(0, $strlen($value) - $cropCount)) . '...',
                true);
        }
        if (gettype($value) == 'array') {
            if (count($value) == 0) {
                return '[]';
            }
            if ($maxLen <= 5) {
                return '[...(' . count($value) . ')]';
            }
            $includeIndices = (
                implode(',', array_keys($value)) !=
                implode(',', array_keys(array_keys($value))));
            $arrayPrefix = '[';
            $arraySuffix = ']';
            $elementSeperator = ', ';
            $indexSeperator = ($includeIndices ? ' => ' : '');
            $firstElementOverheadCharCount =
                strlen("{$arrayPrefix}{$indexSeperator}{$arraySuffix}");
            $additionalElementsOverheadCharCount =
                strlen("{$indexSeperator}{$elementSeperator}");
            $totalOverheadCharCount =
                $firstElementOverheadCharCount +
                ((count($value) - 1) * $additionalElementsOverheadCharCount);
            $contentCharCount = max(0, $maxLen - $totalOverheadCharCount);
            $stringifiedParts = [];
            $stringifiedPartsByLength = [];
            $minElementsToDisplay = 3;
            $minContentLen = 0;
            $processPart = (function ($idx, $type, $partVal) use (&$stringifiedParts, &$stringifiedPartsByLength, &$minContentLen, $minElementsToDisplay, $contentCharCount, $strlen) {
                $shortest = $this->getStringificationOf($partVal, 0);
                $longest = $this->getStringificationOf($partVal, $contentCharCount + 1);
                if ($strlen($shortest) > $strlen($longest)) {
                    $shortest = $longest;
                }
                if ($idx < $minElementsToDisplay) {
                    $minContentLen += $strlen($shortest);
                }
                $stringifiedParts["{$type}_{$idx}"] = [
                    'index'           => $idx,
                    'type'            => $type,
                    'value'           => $partVal,
                    'shortStr'        => $shortest,
                    'longStr'         => $longest,
                    'minLen'          => $strlen($shortest),
                    'maxLen'          => $strlen($longest),
                    'needsTruncation' => ($strlen($longest) > $contentCharCount),
                    'alwaysInclude'   => ($idx < $minElementsToDisplay),
                ];
                if (!isset($stringifiedPartsByLength[$strlen($longest)])) {
                    $stringifiedPartsByLength[$strlen($longest)] = [];
                }
                $stringifiedPartsByLength[$strlen($longest)][] = "{$type}_{$idx} longest";
                if (!isset($stringifiedPartsByLength[$strlen($shortest)])) {
                    $stringifiedPartsByLength[$strlen($shortest)] = [];
                }
                $stringifiedPartsByLength[$strlen($shortest)][] = "{$type}_{$idx} shortest";
            });
            foreach (array_keys($value) as $elementIndex => $key) {
                $val = $value[$keyString];
                if ($includeIndices) {
                    $processPart($elementIndex, 'key', $key);
                }
                $processPart($elementIndex, 'value', $val);
            }
            ksort($stringifiedPartsByLength);
            $partsLeft = count($stringifiedParts);
            $contentCharsLeft = $contentCharCount;
            foreach ($stringifiedPartsByLength as $maxLen => $partIdList) {
                foreach ($partIdList as $partIdAndMode) {
                    list($partId, $mode) = explode(' ', $partIdAndMode, 2);
                    // Don't proces the same part twice.
                    if (!isset($stringifiedParts["{$type}_{$idx}"]['finalString'])) {
                        $partCharAllowance = max(0, floor($contentCharsLeft / $partsLeft));
                        // If we have insufficient length-budget, reserve minimum lengths first.
                        if (
                            ($mode !== 'shortest') ||
                            ($partCharAllowance  > $stringifiedParts["{$type}_{$idx}"]['minLen'])
                        ) {
                            $finalStr = $this->getStringificationOf(
                                $stringifiedParts["{$type}_{$idx}"]['value'],
                                $partCharAllowance);
                            $stringifiedParts["{$type}_{$idx}"]['finalString'] = $finalStr;
                            $contentCharsLeft -= $strlen($finalStr);
                            $partsLeft -= 1;
                        }
                    }
                }
            }
            $truncationEnding = '...(total:' . count($value) . ')';
            $extraTruncationOverheadChars = strlen($truncationEnding) - strlen($elementSeperator);
            $actualContentLength = $contentCharCount - $contentCharsLeft;
            $overflow = ($totalOverheadCharCount + $actualContentLength <= $maxLen);
            if ($overflow) {
                $arraySuffix = "{$truncationEnding}{$arraySuffix}";
            }
            $result = $arrayPrefix;
            $idx = 0;
            $lastItem = '';
            do {
                if ($lastItem !== '') {
                    $result .= "{$lastItem}{$elementSeperator}";
                }
                $lastItem = '';
                if ($includeIndices) {
                    $lastItem .= $stringifiedParts["key_{$idx}"]['finalString'] . $indexSeperator;
                }
                $lastItem .= $stringifiedParts["value_{$idx}"]['finalString'];
                $idx += 1;
            } while (
                ($idx < count($value)) &&
                (
                    !$overflow ||
                    ($strlen("{$result}{$lastItem}{$arraySuffix}") < $maxLen)
                )
            );
            if (!$overflow) {
                $result .= $lastItem;
            }
            $result .= $arraySuffix;
            if (($idx < count($value)) && ($idx < $minElementsToDisplay)) {
                return '[...(' . count($value) . ')]';
            }
            return $result;
        }
        // gettype($value) == 'object'
        if ($strlen(get_class($value)) > $maxLen) {
            return 'object';
        }
        if ($strlen(get_class($value).'Object()') > $maxLen) {
            return get_class($value);
        }
        if ($strlen(get_class($value).'Object()()' . spl_object_hash($value)) > $maxLen) {
            return 'Object(' . get_class($value) . ')';
        }
        if (($this->isConvertableToString($value))) {
            $asStr = (string)$value;
            $maxAsStrLen = $maxLen - $strlen(get_class($value).'Object()(): ' . spl_object_hash($value));
            $result = sprintf(
                'Object(%s(%s: %s))',
                get_class($value),
                spl_object_hash($value),
                $this->getStringificationOf($asStr, $maxAsStrLen)
            );
            if ($strlen($result) <= $maxLen) {
                return $result;
            }
        }
        return sprintf(
            'Object(%s(%s))',
            get_class($value),
            spl_object_hash($value)
        );
    }

    protected function isConvertableToString($value)
    {
        return
            ( !is_array( $value ) ) &&
            ( ( !is_object( $value ) && settype( $value, 'string' ) !== false ) ||
            ( is_object( $value ) && method_exists( $value, '__toString' ) ) );
    }

    protected function assertReflectorValueSame($expected, $actual, $message = '')
    {
        if (!is_object($expected) && !is_array($expected)) {
            $this->assertSame($expected, $actual, $message);
        }
        else if (is_array($expected)) {
            $this->assertInternalType('array', $actual, $message);
            $this->assertCount(count($expected), $actual, $message);
            $actKeys = array_keys($actual);
            foreach (array_keys($expected) as $exIndex => $exKey) {
                $this->assertSame($exKey, $actKeys[$exIndex], $message);
                $this->assertReflectorValueSame($expected[$exKey], $actual[$exKey], $message);
            }
        }
        else if (!($expected instanceof \Reflector)) {
            $this->assertSame($expected, $actual, $message);
        }
        else {
            $this->assertInternalType('object', $actual, $message);
            $this->assertInstanceOf(get_class($expected), $actual, $message);
            $parsedRefClassPat = '/^Go\\\\ParserReflection\\\\/';
            $this->assertEquals(
                preg_replace($parsedRefClassPat, '', get_class($expected)),
                preg_replace($parsedRefClassPat, '', get_class($actual)),
                $message);
            $nativeClassName = preg_replace($parsedRefClassPat, '', get_class($expected));
            $sameObjAssertion = "assertSame{$nativeClassName}";
            $this->assertTrue(method_exists($this, $sameObjAssertion), "Sameness assertion {$sameObjAssertion}() for Reflector " . $this->getStringificationOf($expected) . " " . $message);
            $this->$sameObjAssertion($expected, $actual, $message);
        }
    }

    private function assertSameReflectionExtension($expected, $actual, $message)
    {
        $this->assertSame($expected->getName(),    $actual->getName(),    $message);
        $this->assertSame($expected->getVersion(), $actual->getVersion(), $message);
    }
}
