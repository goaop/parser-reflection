<?php

declare(strict_types=1);
/**
 * Parser Reflection API
 *
 * @copyright Copyright 2024, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\ParserReflection\Resolver;

use Go\ParserReflection\ReflectionClass;
use Go\ParserReflection\ReflectionException;
use Go\ParserReflection\ReflectionFileNamespace;
use Go\ParserReflection\ReflectionIntersectionType;
use Go\ParserReflection\ReflectionNamedType;
use Go\ParserReflection\ReflectionUnionType;
use PhpParser\Node;
use PhpParser\Node\Const_;
use PhpParser\Node\Expr;
use PhpParser\Node\Identifier;
use PhpParser\Node\IntersectionType;
use PhpParser\Node\Name;
use PhpParser\Node\Param;
use PhpParser\Node\Scalar\MagicConst\Line;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Expression;
use PhpParser\PrettyPrinter\Standard;

/**
 * Tries to resolve expression into value
 */
class TypeExpressionResolver
{

    /**
     * Whether this type has explicit null value set
     */
    private bool $hasDefaultNull = false;

    /**
     * Node resolving level, 1 = top-level
     */
    private int $nodeLevel = 0;

    /**
     * @var Node[]
     */
    private array $nodeStack = [];

    private \ReflectionNamedType|\ReflectionUnionType|\ReflectionIntersectionType|null $type;

    public function __construct(
        private readonly ?string $selfClassName = null,
        private readonly ?string $parentClassName = null,
    ) {
    }

    /**
     * @throws ReflectionException If node could not be resolved
     */
    final public function process(Node $node, bool $hasDefaultNull): void
    {
        $this->hasDefaultNull = $hasDefaultNull;
        $this->nodeLevel      = 0;
        $this->nodeStack      = [$node]; // Always keep the root node
        $this->type           = $this->resolve($node);
    }

    final public function getType(): \ReflectionNamedType|\ReflectionUnionType|\ReflectionIntersectionType|null
    {
        return $this->type;
    }

    /**
     * Recursively resolves node into valid type
     *
     * @throws ReflectionException If couldn't resolve value for given Node
     */
    final protected function resolve(Node $node): ReflectionNamedType|ReflectionUnionType|ReflectionIntersectionType|null
    {
        $type = null;
        try {
            $this->nodeStack[] = $node;
            ++$this->nodeLevel;

            $methodName = $this->getDispatchMethodFor($node);
            if (!method_exists($this, $methodName)) {
                throw new ReflectionException("Could not find handler for the " . __CLASS__ . "::{$methodName} method");
            }
            $resolvedType = $this->$methodName($node);
            $type = ($resolvedType instanceof ReflectionNamedType || $resolvedType instanceof ReflectionUnionType || $resolvedType instanceof ReflectionIntersectionType) ? $resolvedType : null;
        } finally {
            array_pop($this->nodeStack);
            --$this->nodeLevel;
        }

        return $type;
    }

    private function resolveUnionType(Node\UnionType $unionType): ReflectionUnionType
    {
        /** @var list<ReflectionIntersectionType|ReflectionNamedType> $resolvedTypes */
        $resolvedTypes = [];
        foreach ($unionType->types as $singleType) {
            $resolved = $this->resolve($singleType);
            if ($resolved instanceof ReflectionIntersectionType || $resolved instanceof ReflectionNamedType) {
                $resolvedTypes[] = $resolved;
            }
        }

        return new ReflectionUnionType(...$resolvedTypes);
    }

    private function resolveIntersectionType(Node\IntersectionType $intersectionType): ReflectionIntersectionType
    {
        /** @var list<ReflectionNamedType> $resolvedTypes */
        $resolvedTypes = [];
        foreach ($intersectionType->types as $singleType) {
            $resolved = $this->resolve($singleType);
            if ($resolved instanceof ReflectionNamedType) {
                $resolvedTypes[] = $resolved;
            }
        }

        return new ReflectionIntersectionType(...$resolvedTypes);
    }

    private function resolveNullableType(Node\NullableType $node): ReflectionNamedType
    {
        $type = $this->resolve($node->type);
        $typeName = $type instanceof ReflectionNamedType ? $type->getName() : '';

        return new ReflectionNamedType($typeName, true, false);
    }

    private function resolveIdentifier(Node\Identifier $node): ReflectionNamedType
    {
        $typeString = $node->toString();
        $allowsNull = $this->hasDefaultNull || in_array($typeString, ['null', 'mixed'], true);

        return new ReflectionNamedType($typeString, $allowsNull, true);
    }

    private function resolveName(Name $node): ReflectionNamedType
    {
        if ($node->hasAttribute('resolvedName')) {
            $resolvedNode = $node->getAttribute('resolvedName');
            if ($resolvedNode instanceof Name) {
                $node = $resolvedNode;
            }
        }

        $typeName = $node->toString();

        // Resolve self/parent to the actual class names when context is available.
        // 'static' is intentionally kept as-is (late static binding, preserved by native reflection).
        if ($typeName === 'self') {
            $typeName = $this->selfClassName ?? $typeName;
        } elseif ($typeName === 'parent') {
            $typeName = $this->parentClassName ?? $typeName;
        }

        return new ReflectionNamedType($typeName, $this->hasDefaultNull, false);
    }

    private function resolveNameFullyQualified(Name\FullyQualified $node): ReflectionNamedType
    {
        return new ReflectionNamedType((string) $node, $this->hasDefaultNull, false);
    }

    private function getDispatchMethodFor(Node $node): string
    {
        $nodeType = $node->getType();

        return 'resolve' . str_replace('_', '', $nodeType);
    }
}
