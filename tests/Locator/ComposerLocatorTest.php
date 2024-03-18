<?php
declare(strict_types=1);

namespace Go\ParserReflection\Locator;

use PHPUnit\Framework\TestCase;
use Go\ParserReflection\ReflectionClass;
use Go\ParserReflection\ReflectionEngine;

class ComposerLocatorTest extends TestCase
{
    public function testLocateClass(): void
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

    public function testLocateClassWithAttributes(): void
    {
        ReflectionEngine::init(new ComposerLocator());

        $parsedClass = new \Go\ParserReflection\ReflectionClass(\Go\ParserReflection\Stub\RandomClassWithAttribute::class);
        $this->assertIsArray($parsedClass->getAttributes());
    }
}
