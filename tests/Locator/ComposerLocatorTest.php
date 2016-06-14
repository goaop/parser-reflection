<?php
namespace Go\ParserReflection\Locator;

use Go\ParserReflection\ReflectionClass;

class ComposerLocatorTest extends \PHPUnit_Framework_TestCase
{
    public function testLocateClass()
    {
        $locator = new ComposerLocator();

        $reflectionClass = new \ReflectionClass(ReflectionClass::class);

        $this->assertSame(
            $reflectionClass->getFileName(),
            realpath($locator->locateClass(ReflectionClass::class))
        );
    }
}
