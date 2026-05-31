<?php

declare(strict_types=1);

namespace Rasuvaeff\Specification\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Rasuvaeff\Specification\ComparisonSpecification;
use Rasuvaeff\Specification\OrSpecification;
use Rasuvaeff\Specification\Specification;
use Rasuvaeff\Specification\SpecificationVisitor;

#[CoversClass(OrSpecification::class)]
final class OrSpecificationTest extends TestCase
{
    #[Test]
    public function emptyConstructor(): void
    {
        $spec = new OrSpecification();

        $this->assertEmpty($spec->getSpecifications());
    }

    #[Test]
    public function constructorWithSpecifications(): void
    {
        $spec1 = new ComparisonSpecification(column: 'status', value: 'active');
        $spec2 = new ComparisonSpecification(column: 'type', value: 'email');

        $spec = new OrSpecification(specifications: [$spec1, $spec2]);

        $specs = $spec->getSpecifications();
        $this->assertCount(2, $specs);
        $this->assertSame($spec1, $specs[0]);
        $this->assertSame($spec2, $specs[1]);
    }

    #[Test]
    public function createFactoryMethod(): void
    {
        $spec1 = $this->createMock(Specification::class);
        $spec2 = $this->createMock(Specification::class);

        $spec = OrSpecification::create($spec1, $spec2);

        $specs = $spec->getSpecifications();
        $this->assertCount(2, $specs);
        $this->assertSame($spec1, $specs[0]);
        $this->assertSame($spec2, $specs[1]);
    }

    #[Test]
    public function createWithNoArguments(): void
    {
        $spec = OrSpecification::create();

        $this->assertEmpty($spec->getSpecifications());
    }

    #[Test]
    public function acceptsVisitor(): void
    {
        $innerSpec = $this->createMock(Specification::class);
        $spec = new OrSpecification(specifications: [$innerSpec]);
        $visitor = $this->createMock(SpecificationVisitor::class);

        $visitor->expects($this->once())
            ->method('visitOr')
            ->with($spec)
            ->willReturn('result');

        $result = $spec->accept(visitor: $visitor);
        $this->assertSame('result', $result);
    }

    #[Test]
    public function acceptWithMultipleSpecifications(): void
    {
        $spec1 = $this->createMock(Specification::class);
        $spec2 = $this->createMock(Specification::class);
        $spec3 = $this->createMock(Specification::class);

        $spec = OrSpecification::create($spec1, $spec2, $spec3);

        $specs = $spec->getSpecifications();
        $this->assertCount(3, $specs);
        $this->assertSame($spec1, $specs[0]);
        $this->assertSame($spec2, $specs[1]);
        $this->assertSame($spec3, $specs[2]);
    }
}
