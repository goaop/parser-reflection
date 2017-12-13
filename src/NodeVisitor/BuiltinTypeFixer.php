<?php
/**
 * Parser Reflection API
 *
 * @copyright Copyright 2015, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\ParserReflection\NodeVisitor;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt;
use PhpParser\NodeVisitorAbstract;

class BuiltinTypeFixer extends NodeVisitorAbstract
{
    const PARAMETER_TYPES = 1;
    const RETURN_TYPES    = 2;

    /** @var array Current list of valid builtin typehints */
    protected $supportedBuiltinTypeHints;

    /**
     * Constructs a name resolution visitor.
     *
     * Options: If "preserveOriginalNames" is enabled, an "originalName" attribute will be added to
     * all name nodes that underwent resolution.
     *
     * @param array $options Options
     */
    public function __construct(array $options = [])
    {
        $this->supportedBuiltinTypeHints = [];
        if (isset($options['supportedBuiltinTypeHints'])) {
            if (!is_array($options['supportedBuiltinTypeHints'])) {
                throw new \InvalidArgumentException(
                    "Option 'supportedBuiltinTypeHints' must be an array."
                );
            }
            $numericIndexCount = count(array_filter(
                $options['supportedBuiltinTypeHints'],
                (function ($val) {
                    return preg_match('/^0*(0|[1-9]\\d*)$/', $val);
                }),
                ARRAY_FILTER_USE_KEY
            ));
            foreach ($options['supportedBuiltinTypeHints'] as $key => $value) {
                $numericIndex = false;
                $typeHintName = $key;
                $validFor     = $value;
                if (preg_match('/^0*(0|[1-9]\\d*)$/', $key) &&
                    (intval($key) >= 0)                     &&
                    (intval($key) <  $numericIndexCount)
                ) {
                    $numericIndex = true;
                    $typeHintName = $value;
                    $validFor     = self::PARAMETER_TYPES|self::RETURN_TYPES;
                }
                if (!is_string($typeHintName) || !preg_match('/^\\w(?<!\\d)\\w*$/', $typeHintName)) {
                    throw new \InvalidArgumentException(
                        sprintf(
                            "Option 'supportedBuiltinTypeHints's element %s isn't a valid typehint string.",
                            var_export($typeHintName, true)
                        )
                    );
                } elseif (!is_scalar($validFor)                                                             ||
                    (strval($validFor) != strval(intval($validFor)))                                        ||
                    (intval($validFor) != (intval($validFor) & (self::PARAMETER_TYPES|self::RETURN_TYPES))) ||
                    (intval($validFor) == 0)
                ) {
                    throw new \InvalidArgumentException(
                        sprintf(
                            "Option 'supportedBuiltinTypeHints's %s typehint applies to invalid mask %s. Mask must be one of: %s::PARAMETER_TYPES (%d), %s::RETURN_TYPES (%d) or %s::PARAMETER_TYPES|%s::RETURN_TYPES (%d)",
                            var_export($typeHintName, true),
                            var_export($validFor, true),
                            self::class,
                            self::PARAMETER_TYPES,
                            self::class,
                            self::RETURN_TYPES,
                            self::class,
                            self::class,
                            (self::PARAMETER_TYPES | self::RETURN_TYPES)
                        )
                    );
                } else {
                    $this->supportedBuiltinTypeHints[$typeHintName] = intval($validFor) & (self::PARAMETER_TYPES|self::RETURN_TYPES);
                }
            }
        } else {
            $phpVersionToSupport = PHP_VERSION_ID; // i.e. 50600
            if (isset($options['phpVersionToSupport'])) {
                $formats = [
                    'version_id'     => '/^0*[57]\\d{4}$/',
                    'version_string' => '/^0*[57]((\\.\d{1,2}){1,2}(\\s*(alpha|beta|[a-z])(\\s*\\d*)))?$/',
                ];
                $matchingFormat = null;
                foreach ($formats as $formatName => $pattern) {
                    if (!$matchingFormat && preg_match($pattern, strval($options['phpVersionToSupport']))) {
                        $matchingFormat = $formatName;
                    }
                }
                if (!$matchingFormat) {
                    throw new \InvalidArgumentException(
                        sprintf(
                            "Option 'phpVersionToSupport' (%s) in unrecognized format.",
                            var_export(strval($options['phpVersionToSupport']), true)
                        )
                    );
                }
                if ($matchingFormat == 'version_string') {
                    $versionParts = explode('.', preg_replace('/\s*[a-z].*$/', '', strval($options['phpVersionToSupport'])));
                    $versionParts = array_slice(array_merge($versionParts, ['0', '0', '0']), 0, 3);
                    $phpVersionToSupport = 0;
                    foreach ($versionParts as $part) {
                        $phpVersionToSupport = intval($part) + (100 * $phpVersionToSupport);
                    }
                } else if ($matchingFormat == 'version_id') {
                    $phpVersionToSupport = intval(strval($options['phpVersionToSupport']));
                }
            }
            $builtInTypeNames = [
                'array'    => [
                    'introduced_version' => 50100,
                    'valid_for'          => self::PARAMETER_TYPES|self::RETURN_TYPES,
                ],
                'callable' => [
                    'introduced_version' => 50400,
                    'valid_for'          => self::PARAMETER_TYPES|self::RETURN_TYPES,
                ],
                'bool'     => [
                    'introduced_version' => 70000,
                    'valid_for'          => self::PARAMETER_TYPES|self::RETURN_TYPES,
                ],
                'float'    => [
                    'introduced_version' => 70000,
                    'valid_for'          => self::PARAMETER_TYPES|self::RETURN_TYPES,
                ],
                'int'      => [
                    'introduced_version' => 70000,
                    'valid_for'          => self::PARAMETER_TYPES|self::RETURN_TYPES,
                ],
                'string'   => [
                    'introduced_version' => 70000,
                    'valid_for'          => self::PARAMETER_TYPES|self::RETURN_TYPES,
                ],
                'iterable' => [
                    'introduced_version' => 70100,
                    'valid_for'          => self::PARAMETER_TYPES|self::RETURN_TYPES,
                ],
                'void'     => [
                    'introduced_version' => 70100,
                    'valid_for'          => self::RETURN_TYPES,
                ],
                'object'   => [
                    'introduced_version' => 70200,
                    'valid_for'          => self::PARAMETER_TYPES|self::RETURN_TYPES,
                ],
            ];
            foreach ($builtInTypeNames as $typeHintName => $valid) {
                if ($phpVersionToSupport >= $valid['introduced_version']) {
                    $this->supportedBuiltinTypeHints[$typeHintName] = $valid['valid_for'];
                }
            }
        }
    }

    public function enterNode(Node $node)
    {
        if (($node instanceof Stmt\Function_)   ||
            ($node instanceof Stmt\ClassMethod) ||
            ($node instanceof Expr\Closure)
        ) {
            $this->fixSignature($node);
        }
    }

    /** @param Stmt\Function_|Stmt\ClassMethod|Expr\Closure $node */
    private function fixSignature($node)
    {
        foreach ($node->params as $param) {
            $param->type = $this->fixType($param->type, self::PARAMETER_TYPES);
        }
        $node->returnType = $this->fixType($node->returnType, self::RETURN_TYPES);
    }

    private function fixType($node, $contextType)
    {
        $typeAsString = $this->getTypeAsString($node);
        if ($typeAsString == '') {
            // $node === null
            return $node;
        }
        if ($node instanceof Node\NullableType) {
            $node->type = $this->fixType($node->type, $contextType);
            return $node;
        }
        $shouldBeBuiltInType = $this->isTypeDefinedForContext($typeAsString, $contextType);
        // This is the actual problem we found:
        //     'object' is being interperted as a builtin typehint
        //     but it isn't.
        if (is_string($node) && !$shouldBeBuiltInType) {
            return new Name($typeAsString);
        }
        // Just in case:
        //     This is the *OPPOSITE* of the issue we're seeing,
        //     where a builtin type could be recognized as a class
        //     name instead.
        if (($node instanceof Name) && $shouldBeBuiltInType) {
            return $typeAsString;
        }
        return $node;
    }

    private function getTypeAsString($node)
    {
        if (!is_null($node) && !is_string($node) && !($node instanceof Name) && !($node instanceof Node\NullableType)) {
            throw new \Exception(sprintf('LOGIC ERROR: %s doesn\'t look like a type. This shouldn\'t get called here.', var_export($node, true)));
        }
        if ($node instanceof Node\NullableType) {
            // This *SHOULD* never be called, but correct behavior shorter than Exception.
            return '?' . $this->getTypeAsString($node->type);
        }
        if ($node instanceof Name) {
            return ($node->isFullyQualified() ? '\\' : '') . $node->toString();
        }
        return strval($node);
    }

    private function isTypeDefinedForContext($typeName, $contextType)
    {
        return
            array_key_exists($typeName, $this->supportedBuiltinTypeHints) &&
            (($contextType & $this->supportedBuiltinTypeHints[$typeName]) == $contextType);
    }
}
