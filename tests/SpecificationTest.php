<?php

declare(strict_types=1);

namespace Rasuvaeff\Specification\Tests;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Rasuvaeff\Specification\Specification;
use Rasuvaeff\Specification\SpecificationVisitor;
use ReflectionClass;

#[CoversNothing]
final class SpecificationTest extends TestCase
{
    #[Test]
    public function interfaceContract(): void
    {
        $reflection = new ReflectionClass(objectOrClass: Specification::class);

        $this->assertTrue($reflection->isInterface());

        $methods = $reflection->getMethods();
        $this->assertCount(1, $methods);

        $this->assertTrue($reflection->hasMethod(name: 'accept'));
        $acceptMethod = $reflection->getMethod(name: 'accept');

        // The return type should be mixed (it is the template type T)
        $returnType = $acceptMethod->getReturnType();
        $this->assertNull($returnType, "Method accept should not have declared return type (uses template T)");

        // Verify the return type PHPDoc
        $methodDocComment = $acceptMethod->getDocComment();
        $this->assertIsString($methodDocComment);
        $this->assertStringContainsString('@param SpecificationVisitor<T> $visitor', $methodDocComment);
        $this->assertStringContainsString('@return T', $methodDocComment);

        $parameters = $acceptMethod->getParameters();
        $this->assertCount(1, $parameters);

        $firstParam = $parameters[0];
        $this->assertSame('visitor', $firstParam->getName());
        $paramType = $firstParam->getType();
        $this->assertInstanceOf(\ReflectionNamedType::class, $paramType);
        $this->assertSame(SpecificationVisitor::class, $paramType->getName());
        $this->assertFalse($firstParam->isOptional());
        $this->assertFalse($firstParam->isDefaultValueAvailable());
        $this->assertFalse($firstParam->allowsNull());
    }

    #[Test]
    public function templateAnnotation(): void
    {
        $reflection = new ReflectionClass(objectOrClass: Specification::class);
        $docComment = $reflection->getDocComment();

        $this->assertIsString($docComment);
        $this->assertStringContainsString('@template T', $docComment);
    }

    #[Test]
    public function noAdditionalPublicMethods(): void
    {
        $reflection = new ReflectionClass(objectOrClass: Specification::class);
        $methods = $reflection->getMethods();

        $this->assertCount(1, $methods);
        $this->assertSame('accept', $methods[0]->getName());
    }

    #[Test]
    public function interfaceHierarchy(): void
    {
        $reflection = new ReflectionClass(objectOrClass: Specification::class);

        $interfaces = $reflection->getInterfaceNames();
        $this->assertEmpty($interfaces, 'SpecificationInterface should not extend any other interfaces');

        $this->assertTrue($reflection->isInterface());
    }
}
