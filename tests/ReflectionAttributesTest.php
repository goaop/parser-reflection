<?php
declare(strict_types=1);

namespace Go\ParserReflection;

use PHPUnit\Framework\TestCase;

class ReflectionAttributesTest extends TestCase
{
    /**
     * @var ReflectionFile
     */
    protected $parsedRefFile;

    public function testGetAttributeOnParameters()
    {
        $this->setUpFile(__DIR__ . '/Stub/FileWithParameters80.php');

        $fileNamespace = $this->parsedRefFile->getFileNamespace('Go\ParserReflection\Stub');
        $parameters = $fileNamespace->getFunction('authenticate')->getParameters();

        foreach ($parameters as $parameter) {
            $attributes = $parameter->getAttributes();
            foreach ($attributes as $attribute) {
                $originalReflectionParameter = new \ReflectionParameter('Go\ParserReflection\Stub\authenticate', $parameter->getName());
                $originalAttribute = current($originalReflectionParameter->getAttributes($attribute->getName()));

                $this->assertInstanceOf(ReflectionAttribute::class, $attribute);
                $this->assertSame($originalAttribute->getName(), $attribute->getName());
                $this->assertSame($originalAttribute->getName(), $attribute->getName());
                $this->assertSame($originalAttribute->getTarget(), $attribute->getTarget());
                $this->assertSame($originalAttribute->getTarget(), $attribute->getTarget());
                $this->assertSame($originalAttribute->getArguments(), $attribute->getArguments());
                $this->assertSame($originalAttribute->isRepeated(), $attribute->isRepeated());
            }
        }
    }

    /**
     * Setups file for parsing
     *
     * @param string $fileName File name to use
     */
    private function setUpFile($fileName)
    {
        $fileName = stream_resolve_include_path($fileName);
        $fileNode = ReflectionEngine::parseFile($fileName);

        $reflectionFile = new ReflectionFile($fileName, $fileNode);
        $this->parsedRefFile = $reflectionFile;

        include_once $fileName;
    }
}
