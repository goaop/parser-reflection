<?php

declare(strict_types=1);
/**
 * Parser Reflection API
 *
 * @copyright Copyright 2026, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\ParserReflection;

use Deprecated;
use Go\ParserReflection\Resolver\NodeExpressionResolver;
use PhpParser\Node\Attribute;
use PhpParser\Node\AttributeGroup;
use PhpParser\Node\Const_ as ConstItemNode;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Const_ as ConstStatementNode;
use Reflector;
use Stringable;

/**
 * AST-based reflection for global and namespaced constants declared with the "const" keyword
 *
 * Native \ReflectionConstant is declared as final, therefore this reflection can not extend it and
 * mirrors its public API instead. Constants defined via "define(...)" are not supported, because they
 * are created at runtime and can not be resolved statically.
 *
 * @see \Go\ParserReflection\ReflectionConstantTest
 */
final class ReflectionConstant implements NodeAwareInterface, Reflector, Stringable
{
    /**
     * Fully-qualified name of the constant, provided to mirror the native reflection property
     */
    public string $name;

    /**
     * Node with the concrete "NAME = value" declaration
     */
    private ConstItemNode $constNode;

    /**
     * Node with the whole "const ...;" statement, it holds attribute groups for the constant
     */
    private ConstStatementNode $declarationNode;

    /**
     * Namespace of the file where this constant is declared, used as a context for expressions
     */
    private ?ReflectionFileNamespace $fileNamespace;

    /**
     * Initializes a reflection for the global or namespaced constant
     *
     * @param string $constantName Fully-qualified name of the constant
     * @param ConstItemNode|null $constNode Optional AST-node with the concrete constant declaration
     * @param ConstStatementNode|null $declarationNode Optional AST-node with the whole "const" statement
     * @param ReflectionFileNamespace|null $fileNamespace Optional namespace to search the constant in
     *
     * @throws ReflectionException if the constant nodes are not given and can not be found
     */
    public function __construct(
        string $constantName,
        ?ConstItemNode $constNode = null,
        ?ConstStatementNode $declarationNode = null,
        ?ReflectionFileNamespace $fileNamespace = null
    ) {
        $this->name          = ltrim($constantName, '\\');
        $this->fileNamespace = $fileNamespace;

        if (!isset($constNode, $declarationNode)) {
            if (!isset($fileNamespace)) {
                throw new ReflectionException(
                    "Could not find the constant " . $this->name . ", because global constants can not be located"
                    . " by name, an AST-node or a file namespace to search in should be given"
                );
            }
            [$declarationNode, $constNode] = self::findConstantNodes($fileNamespace, $this->getShortName());
        }

        $this->constNode       = $constNode;
        $this->declarationNode = $declarationNode;
    }

    /**
     * Emulating original behaviour of reflection
     *
     * @return array<string, string>
     */
    public function __debugInfo(): array
    {
        return ['name' => $this->name];
    }

    /**
     * Returns the fully-qualified name of the constant
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Returns the name of the constant without the namespace prefix
     */
    public function getShortName(): string
    {
        $namespaceParts = explode('\\', $this->name);

        return (string) array_pop($namespaceParts);
    }

    /**
     * Returns the namespace of the constant or an empty string for the global namespace
     */
    public function getNamespaceName(): string
    {
        $namespaceParts = explode('\\', $this->name);
        // Remove the last part with the constant name itself
        array_pop($namespaceParts);

        return implode('\\', $namespaceParts);
    }

    /**
     * Returns the value of the constant, evaluated at the pure AST level
     */
    public function getValue(): mixed
    {
        $expressionSolver = new NodeExpressionResolver($this->fileNamespace);
        $expressionSolver->process($this->constNode->value);

        return $expressionSolver->getValue();
    }

    /**
     * Checks if the constant is marked with the #[\Deprecated] attribute
     *
     * Resolved at the pure AST level, without loading anything into the memory.
     */
    public function isDeprecated(): bool
    {
        foreach ($this->declarationNode->attrGroups as $attrGroup) {
            foreach ($attrGroup->attrs as $attr) {
                if (strcasecmp(self::resolveAttributeName($attr), Deprecated::class) === 0) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Returns the list of attributes, declared for this constant
     *
     * Attributes on constants are available since PHP 8.5, older versions can only parse them.
     *
     * @param class-string<object>|null $name Optional name of the attribute to filter by
     *
     * @return ReflectionAttribute[]
     */
    public function getAttributes(?string $name = null, int $flags = 0): array
    {
        $attributes             = [];
        $nodeExpressionResolver = new NodeExpressionResolver($this->fileNamespace);

        foreach ($this->declarationNode->attrGroups as $attrGroup) {
            foreach ($attrGroup->attrs as $attr) {
                $resolvedAttrName = self::resolveAttributeName($attr);
                if (isset($name) && $name !== $resolvedAttrName) {
                    continue;
                }

                $arguments = [];
                foreach ($attr->args as $arg) {
                    $nodeExpressionResolver->process($arg->value);
                    $arguments[] = $nodeExpressionResolver->getValue();
                }

                $isRepeated   = self::isAttributeRepeated($resolvedAttrName, $this->declarationNode->attrGroups);
                $attributes[] = self::createAttributeReflection($attr, $resolvedAttrName, $arguments, $isRepeated);
            }
        }

        return $attributes;
    }

    /**
     * Returns an AST-node for the concrete constant declaration
     */
    public function getNode(): ConstItemNode
    {
        return $this->constNode;
    }

    /**
     * Returns textual representation of the constant, following the native format
     */
    public function __toString(): string
    {
        $constantValue = $this->getValue();
        $printedValue  = match (true) {
            is_array($constantValue) => 'Array',
            is_scalar($constantValue), $constantValue === null => (string) $constantValue,
            $constantValue instanceof \Stringable => (string) $constantValue,
            default => get_debug_type($constantValue),
        };

        return sprintf(
            "Constant [ %s %s ] { %s }\n",
            get_debug_type($constantValue),
            $this->getName(),
            $printedValue
        );
    }

    /**
     * Searches for the pair of [statement, declaration] nodes for the given constant short name
     *
     * @return array{0: ConstStatementNode, 1: ConstItemNode}
     *
     * @throws ReflectionException if there is no such constant in the given namespace
     */
    private static function findConstantNodes(ReflectionFileNamespace $fileNamespace, string $shortName): array
    {
        // constants can be only top-level nodes in the namespace, so we can scan them directly
        foreach ($fileNamespace->getNode()->stmts as $namespaceLevelNode) {
            if (!$namespaceLevelNode instanceof ConstStatementNode) {
                continue;
            }
            foreach ($namespaceLevelNode->consts as $nodeConstant) {
                if ($nodeConstant->name->toString() === $shortName) {
                    return [$namespaceLevelNode, $nodeConstant];
                }
            }
        }

        throw new ReflectionException(
            "Could not find the constant " . $shortName . " in the file " . $fileNamespace->getFileName()
        );
    }

    /**
     * Normalizes the attribute class name from the given attribute node, without triggering autoloading
     *
     * @return class-string<object>
     */
    private static function resolveAttributeName(Attribute $attributeNode): string
    {
        $attributeNameNode = $attributeNode->name;
        // If we have resolved node name, then we should use it instead
        if ($attributeNameNode->hasAttribute('resolvedName')) {
            $resolvedNameNode = $attributeNameNode->getAttribute('resolvedName');
            if ($resolvedNameNode instanceof Name) {
                $attributeNameNode = $resolvedNameNode;
            }
        }

        return ltrim($attributeNameNode->toString(), '\\');
    }

    /**
     * @param AttributeGroup[] $attrGroups
     */
    private static function isAttributeRepeated(string $attributeName, array $attrGroups): bool
    {
        $count = 0;

        foreach ($attrGroups as $attrGroup) {
            foreach ($attrGroup->attrs as $attr) {
                if (self::resolveAttributeName($attr) === $attributeName) {
                    ++$count;
                }
            }
        }

        return $count >= 2;
    }

    /**
     * Builds a reflection for the given attribute node
     *
     * Attributes are built here instead of the shared AttributeResolverTrait, because the reflector
     * argument of ReflectionAttribute does not accept a constant reflection.
     *
     * @param class-string<object> $attributeName
     * @param array<int, mixed> $arguments
     */
    private static function createAttributeReflection(
        Attribute $attributeNode,
        string $attributeName,
        array $arguments,
        bool $isRepeated
    ): ReflectionAttribute {
        return new class ($attributeNode, $attributeName, $arguments, $isRepeated) extends ReflectionAttribute {
            /**
             * @param class-string<object> $attributeClassName
             * @param array<int, mixed> $attributeArguments
             */
            public function __construct(
                private Attribute $attributeNode,
                private string $attributeClassName,
                private array $attributeArguments,
                private bool $attributeIsRepeated
            ) {}

            public function getNode(): Attribute
            {
                return $this->attributeNode;
            }

            public function getName(): string
            {
                return $this->attributeClassName;
            }

            /**
             * @return array<int, mixed>
             */
            public function getArguments(): array
            {
                return $this->attributeArguments;
            }

            public function isRepeated(): bool
            {
                return $this->attributeIsRepeated;
            }
        };
    }
}
