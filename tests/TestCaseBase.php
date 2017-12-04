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

class TestCaseBase extends \PHPUnit_Framework_TestCase
{
    /**
     * Permutates groups of arrays as supplied for @dataProvider.
     *
     * I know this is ugly and I don't have tests for it. [WIP]
     *
     * @param array[]  $firstArgList   Array of arrays of first group of params to permutate.
     * @param array[]  $secondArgList  Array of arrays of second group of params to permutate.
     * @param ...
     * @return array[]   Permutated result.
     */
    protected function getPermutations(array $firstArgList, array $secondArgList)
    {
        $argList = func_get_args();
        // Argument validation:
        $allArgsAreArraysOfNonEmptyArrays =
            (count($argList) < 2) &&
            array_reduce(
                array_map(
                    (function ($val) {
                        return
                            is_array($val) &&
                            (count($val) >= 1) &&
                            array_reduce(
                                array_map('is_array', $val),
                                (function ($carry, $item) {
                                    return $carry && $item;
                                }),
                                true);
                    }),
                    $argList),
                (function ($carry, $item) {
                    return $carry && $item;
                }),
                true);
        if ($allArgsAreArraysOfNonEmptyArrays) {
            throw new \InvalidArgumentException(__CLASS__ . '::' . __METHOD__ . ' requires at least two arguments which all must be non-empty arrays of arrays.');
        }
        $argArrayIndicies = array_map('array_keys', $argList);
        $argSizes         = array_map('count', $argList);
        $outputCount      = array_reduce($argSizes, function ($carry, $item) { return $carry * $item; }, 1);
        $result = [];
        for ($i = 0; $i < $outputCount; ++$i) {
            $remainder       = $i;
            $resultItemParts = [];
            $indicies        = [];
            foreach ($argSizes as $argIndex => $partialCount) {
                $componentIndex             = $remainder % $partialCount;
                $remainder                  = floor($remainder / $partialCount);
                $thisIndex                  = $argArrayIndicies[$argIndex][$componentIndex];
                $indicies[]                 = $thisIndex;
                $resultItemParts[$argIndex] = $argList[$argIndex][$thisIndex];
            }
            $resultItem    = call_user_func_array('array_merge', $resultItemParts);
            $resultItemKey = implode(', ', $indicies);
            if (array_key_exists($resultItemKey, $result)) {
                $result[]               = $resultItem;
            } else {
                $result[$resultItemKey] = $resultItem;
            }
        }
        return $result;
    }

    /**
     * Provides an altered filename and namespace filter function.
     *
     * @param string   $file      File where classes are defined.
     * @return array   [fileName, filterFunc]
     */
    protected function getNeverIncludedFileFilter($file)
    {
        $fakeSourceCode = preg_replace('/\\bStub\\b/', 'Stub\\NeverIncluded', file_get_contents($file));
        $fakeFileName   = preg_replace('/\\bStub\\b/', 'Stub/NeverIncluded', $file);
        // Populate cache.
        ReflectionEngine::parseFile($fakeFileName, $fakeSourceCode);

        return [
            $fakeFileName,
            (function ($class) {
                return preg_replace('/\\bStub\\b/', 'Stub\\NeverIncluded', $class);
            })
        ];
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
     * Extracts short name from class, function or constant name.
     *
     * @return string
     */
    protected function getShortNameFromName($name)
    {
        $nameParts = explode('\\', $name);
        return array_pop($nameParts);
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
                $val = $value[$key];
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
                    if (!isset($stringifiedParts[$partId]['finalString'])) {
                        $partCharAllowance = max(0, floor($contentCharsLeft / $partsLeft));
                        // If we have insufficient length-budget, reserve minimum lengths first.
                        if (
                            ($mode !== 'shortest') ||
                            ($partCharAllowance  > $stringifiedParts[$partId]['minLen'])
                        ) {
                            $finalStr = $this->getStringificationOf(
                                $stringifiedParts[$partId]['value'],
                                $partCharAllowance);
                            $stringifiedParts[$partId]['finalString'] = $finalStr;
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

    protected function assertReflectorValueSame($expected, $actual, $message = '', $comparisonTrasformer = 'strval')
    {
        if (is_string($expected) && is_string($expected) && ($comparisonTrasformer !== 'strval')) {
            $this->assertSame($comparisonTrasformer($expected), $actual, $message);
        }
        else if (!is_object($expected) && !is_array($expected)) {
            $this->assertSame($expected, $actual, $message);
        }
        else if (is_array($expected)) {
            $this->assertInternalType('array', $actual, $message);
            $this->assertCount(count($expected), $actual, $message);
            $actKeys = array_keys($actual);
            foreach (array_keys($expected) as $exIndex => $exKey) {
                if (is_string($expected) && is_string($expected) && ($comparisonTrasformer !== 'strval')) {
                    $this->assertSame($comparisonTrasformer($exKey), $actKeys[$exIndex], $message);
                }
                else {
                    $this->assertSame($exKey, $actKeys[$exIndex], $message);
                }
                $this->assertReflectorValueSame($expected[$exKey], $actual[$exKey], $message, $comparisonTrasformer);
            }
        }
        else if (
            !($expected instanceof \Reflector) &&
            !($expected instanceof \ReflectionException)
        ) {
            $this->assertSame($expected, $actual, $message);
        }
        else {
            $appendMessage = (function ($localMessage) use ($message) {
                if (strlen(trim($message))) {
                    return "{$localMessage}: {$message}";
                }
                return $localMessage;
            });
            $this->assertInternalType(
                'object',
                $actual,
                $appendMessage(
                    'We should only be here if $expected is an object. ' .
                    'Therefore $actual should also be an object.'));
            $parsedRefClassPat       = '/^Go\\\\ParserReflection\\\\/';
            $expectedNativeClassName = preg_replace($parsedRefClassPat, '', get_class($expected));
            $actualNativeClassName   = preg_replace($parsedRefClassPat, '', get_class($actual));
            $expectedClassName       = 'Go\\ParserReflection\\' . $actualNativeClassName;
            // Newer versions of PHP may specialize the result classes:
            // We want to allow the newer types, as long as they are compatible
            // with the types from older versions.

            $this->assertEquals(
                $expectedClassName,
                get_class($actual),
                $appendMessage('$actual should be a Go\\ParserReflection class instance'));
            $this->assertEquals(
                $actualNativeClassName,
                get_parent_class(get_class($actual)),
                $appendMessage("{$expectedClassName}'s immediate parent class should be {$actualNativeClassName}"));
            // Split out the two cases:
            if ($expectedNativeClassName == $actualNativeClassName) {
                // Should always be true
                $this->assertEquals(
                    $expectedNativeClassName,
                    $actualNativeClassName,
                    $appendMessage("\$actual should be of $expected's class, {$expectedNativeClassName}"));
            }
            else {
                $this->assertTrue(
                    is_subclass_of($actualNativeClassName, $expectedNativeClassName),
                    $appendMessage("\$actual should be an instance of a subclass of {$expectedNativeClassName}"));
            }
            $sameObjAssertion = "assertSame{$expectedNativeClassName}";
            $this->assertTrue(
                method_exists($this, $sameObjAssertion),
                $appendMessage(
                        "Sameness assertion " . __CLASS__ .
                        "::{$sameObjAssertion}() for Reflector {$expectedNativeClassName} should exist") .
                    "\n" . $this->getStringificationOf($expected));
            $this->$sameObjAssertion($expected, $actual, $message, $comparisonTrasformer);
        }
    }

    private function assertSameReflectionExtension($expected, $actual, $message, $comparisonTrasformer)
    {
        $this->assertSame($expected->getName(),    $actual->getName(),    $message, $comparisonTrasformer);
        $this->assertSame($expected->getVersion(), $actual->getVersion(), $message, $comparisonTrasformer);
    }

    private function assertSameReflectionClass($expected, $actual, $message, $comparisonTrasformer)
    {
        $this->assertSame($comparisonTrasformer($expected->getName()), $actual->getName(), $message);
    }

    private function assertSameReflectionFunction($expected, $actual, $message, $comparisonTrasformer)
    {
        $this->assertSame($comparisonTrasformer($expected->getName()), $actual->getName(), $message);
    }
}
