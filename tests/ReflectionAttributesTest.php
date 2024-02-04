<?php
declare(strict_types=1);

namespace Go\ParserReflection;

use PhpParser\Node\Attribute;
use PHPUnit\Framework\TestCase;

class ReflectionAttributesTest extends TestCase
{
    /**
     * @var ReflectionFile
     */
    protected $parsedRefFile;

    public function testGetAttributeOnFunction()
    {
        $this->setUpFile(__DIR__ . '/Stub/FileWithFunction80.php');

        $fileNamespace = $this->parsedRefFile->getFileNamespace('Go\ParserReflection\Stub');
        $function = $fileNamespace->getFunction('function_with_attribute');
        $attributes = $function->getAttributes();

        $originalReflection = new \ReflectionFunction('Go\ParserReflection\Stub\function_with_attribute');

        foreach ($attributes as $attribute) {
            $originalAttribute = current($originalReflection->getAttributes($attribute->getName()));

            $this->assertInstanceOf(ReflectionAttribute::class, $attribute);
            $this->assertInstanceOf(Attribute::class, $attribute->getNode());

            $this->assertSame($originalAttribute->getName(), $attribute->getName());
            $this->assertSame($originalAttribute->getArguments(), $attribute->getArguments());
            $this->assertSame($originalAttribute->isRepeated(), $attribute->isRepeated());
        }
    }

    public function testGetAttributeOnClassMethod()
    {
        $this->setUpFile(__DIR__ . '/Stub/FileWithClassMethod80.php');

        $fileNamespace = $this->parsedRefFile->getFileNamespace('Go\ParserReflection\Stub');
        $class = $fileNamespace->getClass('Go\ParserReflection\Stub\FileWithClassMethod');

        foreach ($class->getMethods() as $method) {
            $attributes = $method->getAttributes();

            $originalReflection = new \ReflectionMethod('Go\ParserReflection\Stub\FileWithClassMethod', $method->getName());

            foreach ($attributes as $attribute) {
                $originalAttribute = current($originalReflection->getAttributes($attribute->getName()));

                $this->assertInstanceOf(ReflectionAttribute::class, $attribute);
                $this->assertInstanceOf(Attribute::class, $attribute->getNode());

                $this->assertSame($originalAttribute->getName(), $attribute->getName());
                $this->assertSame($originalAttribute->getArguments(), $attribute->getArguments());
                $this->assertSame($originalAttribute->isRepeated(), $attribute->isRepeated());
            }

            $this->assertSame($originalReflection->__toString(), $method->__toString());
        }
    }

    public function testGetAttributeOnParameters()
    {
        $this->setUpFile(__DIR__ . '/Stub/FileWithParameters80.php');

        $fileNamespace = $this->parsedRefFile->getFileNamespace('Go\ParserReflection\Stub');
        $parameters = $fileNamespace->getFunction('authenticate')->getParameters();

        foreach ($parameters as $parameter) {
            $attributes = $parameter->getAttributes();
            $originalReflection = new \ReflectionParameter('Go\ParserReflection\Stub\authenticate', $parameter->getName());

            foreach ($attributes as $attribute) {
                $originalAttribute = current($originalReflection->getAttributes($attribute->getName()));

                $this->assertInstanceOf(ReflectionAttribute::class, $attribute);
                $this->assertInstanceOf(Attribute::class, $attribute->getNode());

                $this->assertSame($originalAttribute->getName(), $attribute->getName());
                $this->assertSame($originalAttribute->getArguments(), $attribute->getArguments());
                $this->assertSame($originalAttribute->isRepeated(), $attribute->isRepeated());
            }

            $this->assertSame($originalReflection->__toString(), $parameter->__toString());
        }
    }

    public function testGetAttributeOnClassConst()
    {
        $this->setUpFile(__DIR__ . '/Stub/FileWithClassConst80.php');

        $fileNamespace = $this->parsedRefFile->getFileNamespace('Go\ParserReflection\Stub');
        $constants = $fileNamespace->getClass('Go\ParserReflection\Stub\FileWithClassConstAttribute')->getConstants();

        foreach (array_keys($constants) as $constant) {
            $reflectionClassConst = new ReflectionClassConstant('Go\ParserReflection\Stub\FileWithClassConstAttribute', $constant);
            $attributes = $reflectionClassConst->getAttributes();
            $originalReflection = new \ReflectionClassConstant('Go\ParserReflection\Stub\FileWithClassConstAttribute', $constant);

            foreach ($attributes as $attribute) {
                $originalAttribute = current($originalReflection->getAttributes($attribute->getName()));

                $this->assertInstanceOf(ReflectionAttribute::class, $attribute);
                $this->assertInstanceOf(Attribute::class, $attribute->getNode());

                $this->assertSame($originalAttribute->getName(), $attribute->getName());
                $this->assertSame($originalAttribute->getArguments(), $attribute->getArguments());
                $this->assertSame($originalAttribute->isRepeated(), $attribute->isRepeated());
            }

            $this->assertSame($originalReflection->__toString(), $reflectionClassConst->__toString());
        }
    }


    public function testGetAttributeOnClass()
    {
        $this->setUpFile(__DIR__ . '/Stub/FileWithClass80.php');

        $fileNamespace = $this->parsedRefFile->getFileNamespace('Go\ParserReflection\Stub');
        $class = $fileNamespace->getClass('Go\ParserReflection\Stub\FileWithClassAttribute');

        $attributes = $class->getAttributes();
        $originalReflection = new \ReflectionClass($class->getName());

        foreach ($attributes as $attribute) {
            $originalAttribute = current($originalReflection->getAttributes($attribute->getName()));

            $this->assertInstanceOf(ReflectionAttribute::class, $attribute);
            $this->assertInstanceOf(Attribute::class, $attribute->getNode());

            $this->assertSame($originalAttribute->getName(), $attribute->getName());
            $this->assertSame($originalAttribute->getArguments(), $attribute->getArguments());
            $this->assertSame($originalAttribute->isRepeated(), $attribute->isRepeated());
        }

        $this->assertSame($originalReflection->__toString(), $class->__toString());
    }

    public function testGetAttributeOnProperty()
    {
        $this->setUpFile(__DIR__ . '/Stub/FileWithClassProperty80.php');

        $fileNamespace = $this->parsedRefFile->getFileNamespace('Go\ParserReflection\Stub');
        $properties = $fileNamespace->getClass('Go\ParserReflection\Stub\FileWithClassProperty')->getProperties();

        foreach ($properties as $property) {
            $attributes = $property->getAttributes();
            $originalReflection = new \ReflectionProperty('Go\ParserReflection\Stub\FileWithClassProperty', $property->getName());

            foreach ($attributes as $attribute) {
                $originalAttribute = current($originalReflection->getAttributes($attribute->getName()));

                $this->assertInstanceOf(ReflectionAttribute::class, $attribute);
                $this->assertInstanceOf(Attribute::class, $attribute->getNode());

                $this->assertSame($originalAttribute->getName(), $attribute->getName());
                $this->assertSame($originalAttribute->getArguments(), $attribute->getArguments());
                $this->assertSame($originalAttribute->isRepeated(), $attribute->isRepeated());
            }

            $this->assertSame($originalReflection->__toString(), $property->__toString());
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
