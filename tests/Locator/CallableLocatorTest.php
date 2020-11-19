<?php
declare(strict_types=1);

namespace Go\ParserReflection\Locator;

use PHPUnit\Framework\TestCase;

class CallableLocatorTest extends TestCase
{
    public function testLocateClass()
    {
        $callable = function ($class) {
            return ltrim($class, '\\') . '.php';
        };

        $locator = new CallableLocator($callable);

        $this->assertSame('class.php', $locator->locateClass('class'));
        $this->assertSame('class.php', $locator->locateClass('\class'));
    }
}
