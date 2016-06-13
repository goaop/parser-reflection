<?php
namespace Go\ParserReflection\Locator;

class CallableLocatorTest extends \PHPUnit_Framework_TestCase
{
    public function testLocateClass()
    {
        $callable = function ($class) {
            return $class . '.php';
        };

        $locator = new CallableLocator($callable);

        $this->assertSame('class.php', $locator->locateClass('class'));
    }
}
