<?php

declare(strict_types=1);

namespace Rasuvaeff\Specification\Tests;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Rasuvaeff\Specification\ComparisonSpecification;
use Rasuvaeff\Specification\CompositeSpecification;
use Rasuvaeff\Specification\NotSpecification;
use Rasuvaeff\Specification\OrConditionSpecification;
use Rasuvaeff\Specification\OrSpecification;
use Rasuvaeff\Specification\RawSpecification;
use Rasuvaeff\Specification\SpecificationVisitor;
use ReflectionClass;
use ReflectionMethod;

#[CoversNothing]
final class SpecificationVisitorTest extends TestCase
{
    #[Test]
    public function interfaceContract(): void
    {
        $reflection = new ReflectionClass(objectOrClass: SpecificationVisitor::class);

        $this->assertTrue($reflection->isInterface());

        $docComment = $reflection->getDocComment();
        $this->assertIsString($docComment);
        $this->assertStringContainsString('@template T', $docComment);

        $methods = $reflection->getMethods();
        $this->assertCount(9, $methods);

        // Verify the visitComparison method
        $this->assertTrue($reflection->hasMethod(name: 'visitComparison'));
        $visitComparisonMethod = $reflection->getMethod(name: 'visitComparison');

        // The return type should be mixed (it is the template type T)
        $returnType = $visitComparisonMethod->getReturnType();
        $this->assertNull($returnType, "Method visitComparison should not have declared return type (uses template T)");

        $methodDocComment = $visitComparisonMethod->getDocComment();
        $this->assertIsString($methodDocComment);
        $this->assertStringContainsString('@return T', $methodDocComment);

        $parameters = $visitComparisonMethod->getParameters();
        $this->assertCount(1, $parameters);

        $firstParam = $parameters[0];
        $this->assertSame('specification', $firstParam->getName());
        $paramType = $firstParam->getType();
        $this->assertInstanceOf(\ReflectionNamedType::class, $paramType);
        $this->assertSame(ComparisonSpecification::class, $paramType->getName());
        $this->assertFalse($firstParam->isOptional());
        $this->assertFalse($firstParam->isDefaultValueAvailable());
        $this->assertFalse($firstParam->allowsNull());

        // Verify the visitComposite method
        $this->assertTrue($reflection->hasMethod(name: 'visitComposite'));
        $visitCompositeMethod = $reflection->getMethod(name: 'visitComposite');

        $returnType = $visitCompositeMethod->getReturnType();
        $this->assertNull($returnType);

        $methodDocComment = $visitCompositeMethod->getDocComment();
        $this->assertIsString($methodDocComment);
        $this->assertStringContainsString('@return T', $methodDocComment);

        $parameters = $visitCompositeMethod->getParameters();
        $this->assertCount(1, $parameters);

        $firstParam = $parameters[0];
        $this->assertSame('specification', $firstParam->getName());
        $paramType = $firstParam->getType();
        $this->assertInstanceOf(\ReflectionNamedType::class, $paramType);
        $this->assertSame(CompositeSpecification::class, $paramType->getName());
        $this->assertFalse($firstParam->isOptional());
        $this->assertFalse($firstParam->isDefaultValueAvailable());
        $this->assertFalse($firstParam->allowsNull());

        // Verify the visitNot method
        $this->assertTrue($reflection->hasMethod(name: 'visitNot'));
        $visitNotMethod = $reflection->getMethod(name: 'visitNot');

        $returnType = $visitNotMethod->getReturnType();
        $this->assertNull($returnType);

        $methodDocComment = $visitNotMethod->getDocComment();
        $this->assertIsString($methodDocComment);
        $this->assertStringContainsString('@return T', $methodDocComment);

        $parameters = $visitNotMethod->getParameters();
        $this->assertCount(1, $parameters);

        $firstParam = $parameters[0];
        $this->assertSame('specification', $firstParam->getName());
        $paramType = $firstParam->getType();
        $this->assertInstanceOf(\ReflectionNamedType::class, $paramType);
        $this->assertSame(NotSpecification::class, $paramType->getName());
        $this->assertFalse($firstParam->isOptional());
        $this->assertFalse($firstParam->isDefaultValueAvailable());
        $this->assertFalse($firstParam->allowsNull());

        // Verify the visitOr method
        $this->assertTrue($reflection->hasMethod(name: 'visitOr'));
        $visitOrMethod = $reflection->getMethod(name: 'visitOr');

        $returnType = $visitOrMethod->getReturnType();
        $this->assertNull($returnType);

        $methodDocComment = $visitOrMethod->getDocComment();
        $this->assertIsString($methodDocComment);
        $this->assertStringContainsString('@return T', $methodDocComment);

        $parameters = $visitOrMethod->getParameters();
        $this->assertCount(1, $parameters);

        $firstParam = $parameters[0];
        $this->assertSame('specification', $firstParam->getName());
        $paramType = $firstParam->getType();
        $this->assertInstanceOf(\ReflectionNamedType::class, $paramType);
        $this->assertSame(OrSpecification::class, $paramType->getName());
        $this->assertFalse($firstParam->isOptional());
        $this->assertFalse($firstParam->isDefaultValueAvailable());
        $this->assertFalse($firstParam->allowsNull());

        // Verify the visitOrCondition method
        $this->assertTrue($reflection->hasMethod(name: 'visitOrCondition'));
        $visitOrConditionMethod = $reflection->getMethod(name: 'visitOrCondition');

        $returnType = $visitOrConditionMethod->getReturnType();
        $this->assertNull($returnType);

        $methodDocComment = $visitOrConditionMethod->getDocComment();
        $this->assertIsString($methodDocComment);
        $this->assertStringContainsString('@return T', $methodDocComment);

        $parameters = $visitOrConditionMethod->getParameters();
        $this->assertCount(1, $parameters);

        $firstParam = $parameters[0];
        $this->assertSame('specification', $firstParam->getName());
        $paramType = $firstParam->getType();
        $this->assertInstanceOf(\ReflectionNamedType::class, $paramType);
        $this->assertSame(OrConditionSpecification::class, $paramType->getName());
        $this->assertFalse($firstParam->isOptional());
        $this->assertFalse($firstParam->isDefaultValueAvailable());
        $this->assertFalse($firstParam->allowsNull());

        // Verify the visitRaw method
        $this->assertTrue($reflection->hasMethod(name: 'visitRaw'));
        $visitRawMethod = $reflection->getMethod(name: 'visitRaw');

        $returnType = $visitRawMethod->getReturnType();
        $this->assertNull($returnType);

        $methodDocComment = $visitRawMethod->getDocComment();
        $this->assertIsString($methodDocComment);
        $this->assertStringContainsString('@return T', $methodDocComment);

        $parameters = $visitRawMethod->getParameters();
        $this->assertCount(1, $parameters);

        $firstParam = $parameters[0];
        $this->assertSame('specification', $firstParam->getName());
        $paramType = $firstParam->getType();
        $this->assertInstanceOf(\ReflectionNamedType::class, $paramType);
        $this->assertSame(RawSpecification::class, $paramType->getName());
        $this->assertFalse($firstParam->isOptional());
        $this->assertFalse($firstParam->isDefaultValueAvailable());
        $this->assertFalse($firstParam->allowsNull());

        $methodNames = array_map(
            callback: static fn(ReflectionMethod $method): string => $method->getName(),
            array: $methods,
        );

        $expectedMethods = [
            'visitComparison',
            'visitComposite',
            'visitNot',
            'visitOr',
            'visitOrCondition',
            'visitRaw',
            'visitOrderBy',
            'visitLimit',
            'visitOffset',
        ];

        foreach ($expectedMethods as $expectedMethod) {
            $this->assertContains($expectedMethod, $methodNames);
        }
    }
}
