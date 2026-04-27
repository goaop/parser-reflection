# Parser Reflection Library

Always reference these instructions first and fallback to search or bash commands only when you encounter unexpected information that does not match the info here.

Parser Reflection is a **deprecated** PHP library that provides reflection capabilities without loading classes into memory. It extends PHP's internal reflection classes using nikic/PHP-Parser for static code analysis. The library is fully functional but deprecated in favor of [BetterReflection](https://github.com/Roave/BetterReflection).

## Working Effectively

### Bootstrap, Build and Test the Repository

**CRITICAL: Set timeouts of 30+ minutes for all composer commands. NEVER CANCEL composer operations.**

1. **Verify PHP version**: Requires PHP >=8.2
   ```bash
   php --version  # Should show PHP 8.2+
   ```

2. **Install dependencies** (takes 15-25 minutes due to GitHub API rate limits):
   ```bash
   composer config --global process-timeout 2000
   composer install --prefer-source --no-interaction
   ```
   **NEVER CANCEL** - This process takes 15-25 minutes and may show authentication warnings. The `--prefer-source` flag is REQUIRED to avoid GitHub API rate limit issues.

3. **Generate autoloader** (if not created during install):
   ```bash
   composer dump-autoload
   ```

4. **Run tests** - Takes ~6 seconds. NEVER CANCEL timeout should be 30+ minutes for safety:
   ```bash
   vendor/bin/phpunit  # 10,579 tests, ~6 seconds
   ```

### Code Quality and Validation

5. **Run static analysis** - Takes ~5 seconds:
   ```bash
   vendor/bin/phpstan analyse src --no-progress  # Expect 18 existing errors (normal)
   ```

6. **Run code quality checks** - Takes ~5 seconds:
   ```bash
   vendor/bin/rector --dry-run  # Shows suggested improvements, don't auto-apply
   ```

7. **Validate composer.json**:
   ```bash
   composer validate  # Should complete in <1 second
   ```

### Test Library Functionality

Always test changes by running actual reflection scenarios:

```bash
# Create test script
cat > /tmp/test_reflection.php << 'EOF'
<?php
require_once 'vendor/autoload.php';

$parsedFile = new \Go\ParserReflection\ReflectionFile('src/ReflectionClass.php');
$namespaces = $parsedFile->getFileNamespaces();
foreach ($namespaces as $namespace) {
    $classes = $namespace->getClasses();
    foreach ($classes as $class) {
        echo "Found class: " . $class->getName() . " with " . count($class->getMethods()) . " methods\n";
    }
}
EOF

php /tmp/test_reflection.php
```

## Repository Structure

### Key Directories
- `src/` - Main library code (30 PHP files)
  - Core reflection classes (ReflectionClass, ReflectionMethod, etc.)
  - `bootstrap.php` - Auto-initialization
  - `Locator/` - Class location logic
  - `Traits/` - Shared functionality
- `tests/` - Test suite (37 test files, 10,579 tests)
- `docs/` - API documentation for each reflection class
- `vendor/` - Dependencies (created during build)

### Important Files
- `composer.json` - Dependencies: php >=8.2, nikic/php-parser ^5.0
- `phpunit.xml.dist` - Test configuration (1536M memory limit)
- `rector.php` - Code quality rules
- `.github/workflows/phpunit.yml` - CI pipeline (PHP 8.2, 8.3, 8.4)

## Common Issues and Troubleshooting

### Network/Authentication Issues
- **`composer install` fails with GitHub authentication errors**: Use `composer install --prefer-source --no-interaction`
- **SSL timeout errors**: Increase timeout with `composer config --global process-timeout 2000`
- **API rate limits**: The `--prefer-source` flag bypasses GitHub API limits by cloning repositories directly

### Build Issues
- **Missing vendor/autoload.php**: Run `composer dump-autoload`
- **Class not found errors**: Ensure composer install completed successfully
- **Memory errors during tests**: Tests are configured with 1536M memory limit in phpunit.xml.dist

### Library Usage
- **"Class not found by locator" errors**: This is expected for classes not autoloaded by Composer
- **Parser errors**: Library only works with valid PHP syntax
- **Missing nikic/php-parser**: This is the core dependency - ensure composer install succeeded

## Validation Scenarios

**ALWAYS test these scenarios after making changes:**

1. **Basic reflection test**:
   ```bash
   php -r "require 'vendor/autoload.php'; \$f = new \Go\ParserReflection\ReflectionFile('src/ReflectionClass.php'); \$ns = \$f->getFileNamespaces(); foreach(\$ns as \$n) { echo 'Classes in ' . \$n->getName() . ': ' . count(\$n->getClasses()) . \"\n\"; }"
   ```

2. **Run core test suite**:
   ```bash
   vendor/bin/phpunit --testsuite="Parser Reflection Test Suite"
   ```

3. **Verify no syntax errors**:
   ```bash
   find src -name "*.php" -exec php -l {} \; | grep -v "No syntax errors"
   ```

## Build Timing Expectations

**NEVER CANCEL - All timing includes 50% safety buffer:**

- `composer install --prefer-source`: **15-25 minutes** (NEVER CANCEL - set 30+ minute timeout)
- `vendor/bin/phpunit`: **~6 seconds** (set 30+ minute timeout for safety)
- `vendor/bin/rector --dry-run`: **~5 seconds** (set 10+ minute timeout)
- `vendor/bin/phpstan analyse src`: **~5 seconds** (set 10+ minute timeout)
- Basic syntax check: **<10 seconds** (set 5+ minute timeout)

## Development Notes

- This library is **deprecated** - prefer BetterReflection for new projects
- Library provides drop-in replacements for PHP's reflection classes
- Core functionality requires nikic/php-parser for AST generation
- Tests use Composer's autoloader for class location
- Memory usage can be high for large codebases (configure php.ini accordingly)
- Compatible with PHP 8.2+ (tested on 8.2, 8.3, 8.4)

## CI/Build Pipeline Reference

The GitHub Actions pipeline (`.github/workflows/phpunit.yml`) runs:
- Matrix testing: PHP 8.2, 8.3, 8.4 on Ubuntu
- Dependency variations: lowest, highest
- Standard `composer install` (works in CI with GitHub tokens)
- PHPUnit test suite execution