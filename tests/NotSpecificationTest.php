<?php

declare(strict_types=1);

namespace Rasuvaeff\Specification\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Rasuvaeff\Specification\ComparisonSpecification;
use Rasuvaeff\Specification\NotSpecification;
use Rasuvaeff\Specification\Specification;
use Rasuvaeff\Specification\SpecificationVisitor;

#[CoversClass(NotSpecification::class)]
final class NotSpecificationTest extends TestCase
{
    #[Test]
    public function constructorAndGetter(): void
    {
        $innerSpec = $this->createMock(Specification::class);
        $spec = new NotSpecification(specification: $innerSpec);

        $this->assertSame($innerSpec, $spec->getSpecification());
    }

    #[Test]
    public function createFactoryMethod(): void
    {
        $innerSpec = $this->createMock(Specification::class);
        $spec = NotSpecification::create(specification: $innerSpec);

        $this->assertInstanceOf(NotSpecification::class, $spec);
        $this->assertSame($innerSpec, $spec->getSpecification());
    }

    #[Test]
    public function acceptsVisitor(): void
    {
        $innerSpec = $this->createMock(Specification::class);
        $spec = new NotSpecification(specification: $innerSpec);
        $visitor = $this->createMock(SpecificationVisitor::class);

        $visitor->expects($this->once())
            ->method('visitNot')
            ->with($spec)
            ->willReturn('result');

        $result = $spec->accept(visitor: $visitor);
        $this->assertSame('result', $result);
    }

    #[Test]
    public function withComparisonSpecification(): void
    {
        $innerSpec = new ComparisonSpecification(column: 'status', value: 'active');
        $spec = new NotSpecification(specification: $innerSpec);

        $this->assertSame($innerSpec, $spec->getSpecification());
    }
}
