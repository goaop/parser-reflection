# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Overview

Parser Reflection is a **deprecated** PHP library (deprecated in favor of [BetterReflection](https://github.com/Roave/BetterReflection)) that extends PHP's internal reflection classes using nikic/PHP-Parser for static analysis. It reflects PHP code without loading classes into memory by parsing source files into an AST.

Requires PHP >=8.2. Namespace: `Go\ParserReflection\`.

## Commands

```bash
# Install dependencies (slow locally — see note below)
composer install --prefer-source --no-interaction

# Run tests (~6 seconds, ~10,500 tests)
vendor/bin/phpunit

# Run a single test file
vendor/bin/phpunit tests/ReflectionClassTest.php

# Run a specific test method
vendor/bin/phpunit --filter testMethodName

# Static analysis (~5 seconds, 18 known existing errors are normal)
vendor/bin/phpstan analyse src --no-progress
```

> **Note on `composer install` locally**: due to GitHub API rate limits, use `--prefer-source` and set a long timeout: `composer config --global process-timeout 2000`. In CI, standard `composer install` works fine with GitHub tokens.

## Architecture

### Request flow

When you call `new ReflectionClass('SomeClass')`:
1. `ReflectionClass` asks `ReflectionEngine` for the class's AST node
2. `ReflectionEngine` uses the registered `LocatorInterface` to find the file
3. The file is parsed by PHP-Parser into an AST
4. Two node visitors run: `NameResolver` (resolves FQCNs) and `RootNamespaceNormalizer` (normalizes global namespace)
5. The resulting `ClassLike` AST node is stored in `ReflectionEngine::$parsedFiles` (in-memory LRU cache)
6. The node is wrapped in the appropriate reflection class

### Key components

- **`ReflectionEngine`** (`src/ReflectionEngine.php`) — static class; central hub. Owns the PHP-Parser instance, AST cache, and locator. Entry points: `parseFile()`, `parseClass()`, `parseClassMethod()`, etc.
- **`LocatorInterface`** / **`ComposerLocator`** — pluggable class file finder. `ComposerLocator` delegates to Composer's classmap/autoloader. `bootstrap.php` auto-registers `ComposerLocator` on load.
- **Reflection classes** (`src/Reflection*.php`) — each extends its PHP internal counterpart (e.g. `ReflectionClass extends \ReflectionClass`) and holds an AST node. Methods that require a live object (e.g. `invoke()`) trigger actual class loading and fall back to native reflection.
- **Traits** (`src/Traits/`) — shared logic extracted to avoid duplication:
  - `ReflectionClassLikeTrait` — used by `ReflectionClass`; implements most class inspection methods against the AST
  - `ReflectionFunctionLikeTrait` — shared by `ReflectionMethod` and `ReflectionFunction`
  - `InitializationTrait` — lazy initialization of AST node from engine
  - `InternalPropertiesEmulationTrait` — makes `var_dump`/serialization look like native reflection
  - `AttributeResolverTrait` — resolves PHP 8 attributes from AST nodes
- **Resolvers** (`src/Resolver/`) — `NodeExpressionResolver` evaluates constant expressions in the AST (used for default values, constants). `TypeExpressionResolver` resolves type AST nodes into reflection type objects.
- **`ReflectionFile` / `ReflectionFileNamespace`** — library-specific (not in native PHP reflection). Allow reflecting arbitrary PHP files and iterating their namespaces, classes, functions without knowing class names in advance.

### Test structure

Tests in `tests/` mirror the reflection class names (e.g. `ReflectionClassTest.php`). PHP version-specific stub files in `tests/Stub/` (e.g. `FileWithClasses84.php`) contain the PHP code being reflected. Tests extend `AbstractTestCase` which sets up the `ReflectionEngine` with a `ComposerLocator`.

### CI

GitHub Actions (`.github/workflows/phpunit.yml`) runs PHPUnit on PHP 8.2, 8.3, 8.4 with both lowest and highest dependency versions.
