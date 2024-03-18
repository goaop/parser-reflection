<?php
declare(strict_types=1);

namespace Go\ParserReflection;

use Go\ParserReflection\Stub\ClassPHP80WithAttribute;
use PhpParser\Node\Attribute;
use PHPUnit\Framework\TestCase;

class ReflectionAttributesTest extends TestCase
{
    protected ReflectionFile $parsedRefFile;

    public function testGetAttributeOnFunction(): void
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

    public function testGetAttributeOnClassMethod(): void
    {
        $this->setUpFile(__DIR__ . '/Stub/FileWithClasses80.php');

        $fileNamespace = $this->parsedRefFile->getFileNamespace('Go\ParserReflection\Stub');
        $class = $fileNamespace->getClass(ClassPHP80WithAttribute::class);

        foreach ($class->getMethods() as $method) {
            $attributes = $method->getAttributes();

            $originalReflection = new \ReflectionMethod(ClassPHP80WithAttribute::class, $method->getName());

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

    public function testGetAttributeOnParameters(): void
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

    public function testGetAttributeOnClassConst(): void
    {
        $this->setUpFile(__DIR__ . '/Stub/FileWithClasses80.php');

        $fileNamespace = $this->parsedRefFile->getFileNamespace('Go\ParserReflection\Stub');
        $constants = $fileNamespace->getClass(ClassPHP80WithAttribute::class)->getConstants();

        foreach (array_keys($constants) as $constant) {
            $reflectionClassConst = new ReflectionClassConstant(ClassPHP80WithAttribute::class, $constant);
            $attributes = $reflectionClassConst->getAttributes();
            $originalReflection = new \ReflectionClassConstant(ClassPHP80WithAttribute::class, $constant);

            foreach ($attributes as $key => $attribute) {
                $originalAttributes = $originalReflection->getAttributes($attribute->getName());

                $this->assertInstanceOf(ReflectionAttribute::class, $attribute);
                $this->assertInstanceOf(Attribute::class, $attribute->getNode());

                $this->assertSame(current($originalAttributes)->getName(), $attribute->getName());
                $this->assertSame(current($originalAttributes)->isRepeated(), $attribute->isRepeated());

                // test repeated on constant stub
                if ($attribute->isRepeated()) {
                    $this->assertSame($originalAttributes[$key]->getArguments(), $attribute->getArguments());
                }
            }

            $this->assertSame($originalReflection->__toString(), $reflectionClassConst->__toString());
        }
    }


    public function testGetAttributeOnClass(): void
    {
        $this->setUpFile(__DIR__ . '/Stub/FileWithClasses80.php');

        $fileNamespace = $this->parsedRefFile->getFileNamespace('Go\ParserReflection\Stub');
        $class = $fileNamespace->getClass(ClassPHP80WithAttribute::class);

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
    }

    public function testGetAttributeOnProperty(): void
    {
        $this->setUpFile(__DIR__ . '/Stub/FileWithClasses80.php');

        $fileNamespace = $this->parsedRefFile->getFileNamespace('Go\ParserReflection\Stub');
        $properties = $fileNamespace->getClass(ClassPHP80WithAttribute::class)->getProperties();

        foreach ($properties as $property) {
            $attributes = $property->getAttributes();
            $originalReflection = new \ReflectionProperty(ClassPHP80WithAttribute::class, $property->getName());

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
     */
    private function setUpFile(string $fileName): void
    {
        $fileName = stream_resolve_include_path($fileName);
        $fileNode = ReflectionEngine::parseFile($fileName);

        $reflectionFile = new ReflectionFile($fileName, $fileNode);
        $this->parsedRefFile = $reflectionFile;

        include_once $fileName;
    }
}
