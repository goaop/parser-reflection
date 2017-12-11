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

class BuiltinAliasFixer extends NodeVisitorAbstract
{
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
    public function __construct(array $options = []) {
        $this->supportedBuiltinTypeHints = [];
        if (isset($options['supportedBuiltinTypeHints'])) {
            if (!is_array($options['supportedBuiltinTypeHints'])) {
                throw new \InvalidArgumentException(
                    "option 'supportedBuiltinTypeHints' must be an array."
                );
            }
            foreach ($options['supportedBuiltinTypeHints'] as $index => $typeHintName) {
                if (!is_string($typeHintName) || !preg_match('/^\\w+$/', $typeHintName)) {
                    throw new \InvalidArgumentException(
                        sprintf(
                            "All elements of option 'supportedBuiltinTypeHints' array " .
                                "must be a single word string. Element %s (%s) isn't valid.",
                            $index,
                            var_export($typeHintName, true)
                        )
                    );
                } else {
                    $this->supportedBuiltinTypeHints[] = $typeHintName;
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
                'array'    => 50100,
                'callable' => 50400,
                'bool'     => 70000,
                'float'    => 70000,
                'int'      => 70000,
                'string'   => 70000,
                'iterable' => 70100,
            ];
            foreach ($builtInTypeNames as $typeHintName => $verIdIntroduced) {
                if ($phpVersionToSupport >= $verIdIntroduced) {
                    $this->supportedBuiltinTypeHints[] = $typeHintName;
                }
            }
        }
        // error_log(var_export([
        //     '$this->supportedBuiltinTypeHints' => $this->supportedBuiltinTypeHints,
        //     '$phpVersionToSupport' => $phpVersionToSupport,
        //     '$builtInTypeNames' => $builtInTypeNames,
        //     'isset($options[\'supportedBuiltinTypeHints\'])' => isset($options['supportedBuiltinTypeHints']),
        // ], true));
    }

    public function enterNode(Node $node) {
        if (
            ($node instanceof Stmt\Function_)   ||
            ($node instanceof Stmt\ClassMethod) ||
            ($node instanceof Expr\Closure)
        ) {
            $this->fixSignature($node);
        }
    }

    /** @param Stmt\Function_|Stmt\ClassMethod|Expr\Closure $node */
    private function fixSignature($node) {
        foreach ($node->params as $param) {
            $param->type = $this->fixType($param->type);
        }
        $node->returnType = $this->fixType($node->returnType);
    }

    private function fixType($node) {
        if (!is_object($node) && (strval($node) == '')) {
            return $node;
        }
        if ($node instanceof Node\NullableType) {
            $node->type = $this->fixType($node->type);
            return $node;
        }
        // This is the actual problem we found:
        //     'object' is being interperted as a builtin typehint
        //     but it isn't.
        if (!is_object($node) && !in_array(strval($node), $this->supportedBuiltinTypeHints)) {
            return new Name(strval($node));
        }
        // Just in case:
        //     This is the *OPPOSITE* of the issue we're seeing,
        //     where a builtin type could be recognized as a class
        //     name instead.
        if (
            ($node instanceof Name)      &&
            !($node->isFullyQualified()) &&
            (count($node->parts) == 1)   &&
            in_array(strval(implode('\\', $node->parts)), $this->supportedBuiltinTypeHints)
        ) {
            return strval(implode('\\', $node->parts));
        }
        return $node;
    }
}
