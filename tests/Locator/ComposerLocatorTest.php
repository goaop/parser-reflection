<?php
namespace Go\ParserReflection\Locator;

use PHPUnit\Framework\TestCase;
use Go\ParserReflection\ReflectionClass;

class ComposerLocatorTest extends TestCase
{
    public function testLocateClass()
    {
        $locator         = new ComposerLocator();
        $reflectionClass = new \ReflectionClass(ReflectionClass::class);
        $this->assertSame(
            $reflectionClass->getFileName(),
            $locator->locateClass(ReflectionClass::class)
        );
        $this->assertSame(
            $reflectionClass->getFileName(),
            $locator->locateClass('\\' . ReflectionClass::class)
        );
    }
}
