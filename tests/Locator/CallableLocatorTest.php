<?php
declare(strict_types=1);

namespace Go\ParserReflection\Locator;

use PHPUnit\Framework\TestCase;

class CallableLocatorTest extends TestCase
{
    public function testLocateClass(): void
    {
        $callable = fn($class) => ltrim($class, '\\') . '.php';

        $locator = new CallableLocator($callable);

        $this->assertSame('class.php', $locator->locateClass('class'));
        $this->assertSame('class.php', $locator->locateClass('\class'));
    }
}
