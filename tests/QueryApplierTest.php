<?php

declare(strict_types=1);

namespace Rasuvaeff\Specification\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Rasuvaeff\Specification\ComparisonSpecification;
use Rasuvaeff\Specification\QueryApplier;
use ReflectionClass;
use Yiisoft\Db\Query\QueryInterface;

#[CoversClass(QueryApplier::class)]
final class QueryApplierTest extends TestCase
{
    private MockObject&QueryInterface $queryMock;

    #[\Override]
    protected function setUp(): void
    {
        $this->queryMock = $this->createMock(QueryInterface::class);
    }

    #[Test]
    public function applyWithComparisonSpecification(): void
    {
        $spec = new ComparisonSpecification(column: 'status', value: 'active');

        $this->queryMock->expects($this->once())
            ->method('andWhere')
            ->with(['=', 'status', 'active']);

        QueryApplier::apply(specification: $spec, query: $this->queryMock);
    }

    #[Test]
    public function applyCreatesVisitor(): void
    {
        $spec = new ComparisonSpecification(column: 'age', value: 25, operator: '>');

        $this->queryMock->expects($this->once())
            ->method('andWhere')
            ->with(['>', 'age', 25]);

        QueryApplier::apply(specification: $spec, query: $this->queryMock);
    }

    #[Test]
    public function staticMethod(): void
    {
        $reflection = new ReflectionClass(objectOrClass: QueryApplier::class);
        $methods = $reflection->getMethods();

        // Verify there is exactly one public static method
        $staticMethods = array_filter(array: $methods, callback: static fn(\ReflectionMethod $m): bool => $m->isStatic() && $m->isPublic());
        $this->assertCount(1, $staticMethods);
        $this->assertSame('apply', $staticMethods[0]->getName());
    }
}
