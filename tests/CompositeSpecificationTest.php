<?php

declare(strict_types=1);

namespace Rasuvaeff\Specification\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Rasuvaeff\Specification\ComparisonSpecification;
use Rasuvaeff\Specification\CompositeSpecification;
use Rasuvaeff\Specification\LimitSpecification;
use Rasuvaeff\Specification\NotSpecification;
use Rasuvaeff\Specification\OrConditionSpecification;
use Rasuvaeff\Specification\OrderBySpecification;
use Rasuvaeff\Specification\RawSpecification;
use Rasuvaeff\Specification\Specification;
use Rasuvaeff\Specification\SpecificationVisitor;

#[CoversClass(CompositeSpecification::class)]
final class CompositeSpecificationTest extends TestCase
{
    #[Test]
    public function emptyConstructor(): void
    {
        $spec = new CompositeSpecification();
        $this->assertEmpty($spec->getSpecifications());
    }

    #[Test]
    public function constructorWithSpecifications(): void
    {
        $spec1 = $this->createMock(Specification::class);
        $spec2 = $this->createMock(Specification::class);

        $spec = new CompositeSpecification(specifications: [$spec1, $spec2]);

        $specifications = $spec->getSpecifications();
        $this->assertCount(2, $specifications);
        $this->assertSame($spec1, $specifications[0]);
        $this->assertSame($spec2, $specifications[1]);
    }

    #[Test]
    public function withSpecification(): void
    {
        $spec1 = $this->createMock(Specification::class);
        $spec2 = $this->createMock(Specification::class);

        $composite = new CompositeSpecification(specifications: [$spec1]);
        $newComposite = $composite->withSpecification(specification: $spec2);

        // Original should not be modified
        $this->assertCount(1, $composite->getSpecifications());

        // New composite should have both
        $specifications = $newComposite->getSpecifications();
        $this->assertCount(2, $specifications);
        $this->assertSame($spec1, $specifications[0]);
        $this->assertSame($spec2, $specifications[1]);
    }

    #[Test]
    public function withOrCondition(): void
    {
        $composite = new CompositeSpecification();
        $newComposite = $composite->withOrCondition(conditions: [
            'status' => 'active',
            'type' => 'email',
        ]);

        $specifications = $newComposite->getSpecifications();
        $this->assertCount(1, $specifications);
        $this->assertInstanceOf(OrConditionSpecification::class, $specifications[0]);
    }

    #[Test]
    public function withNot(): void
    {
        $innerSpec = $this->createMock(Specification::class);
        $composite = new CompositeSpecification();

        $newComposite = $composite->withNot(specification: $innerSpec);

        $specifications = $newComposite->getSpecifications();
        $this->assertCount(1, $specifications);
        $this->assertInstanceOf(NotSpecification::class, $specifications[0]);
    }

    #[Test]
    public function create(): void
    {
        $spec = CompositeSpecification::create();
        $this->assertInstanceOf(CompositeSpecification::class, $spec);
        $this->assertEmpty($spec->getSpecifications());
    }

    #[Test]
    public function withComparison(): void
    {
        $composite = new CompositeSpecification();
        $newComposite = $composite->withComparison(column: 'status', value: 'active');

        $specifications = $newComposite->getSpecifications();
        $this->assertCount(1, $specifications);
        $this->assertInstanceOf(ComparisonSpecification::class, $specifications[0]);
    }

    #[Test]
    public function withSpecificationAppendsSpec(): void
    {
        $spec = $this->createMock(Specification::class);
        $composite = new CompositeSpecification();

        $newComposite = $composite->withSpecification(specification: $spec);

        $specifications = $newComposite->getSpecifications();
        $this->assertCount(1, $specifications);
        $this->assertSame($spec, $specifications[0]);
    }

    #[Test]
    public function withOrderBy(): void
    {
        $composite = new CompositeSpecification();
        $newComposite = $composite->withOrderBy(columns: ['created_at' => 'DESC']);

        $specifications = $newComposite->getSpecifications();
        $this->assertCount(1, $specifications);
        $this->assertInstanceOf(OrderBySpecification::class, $specifications[0]);
    }

    #[Test]
    public function withLimit(): void
    {
        $composite = new CompositeSpecification();
        $newComposite = $composite->withLimit(limit: 10);

        $specifications = $newComposite->getSpecifications();
        $this->assertCount(1, $specifications);
        $this->assertInstanceOf(LimitSpecification::class, $specifications[0]);
    }

    #[Test]
    public function acceptsVisitor(): void
    {
        $spec1 = $this->createMock(Specification::class);
        $spec2 = $this->createMock(Specification::class);

        $composite = new CompositeSpecification(specifications: [$spec1, $spec2]);
        $visitor = $this->createMock(SpecificationVisitor::class);

        $visitor->expects($this->once())
            ->method('visitComposite')
            ->with($composite)
            ->willReturn('result');

        $result = $composite->accept(visitor: $visitor);
        $this->assertSame('result', $result);
    }

    #[Test]
    public function withRaw(): void
    {
        $composite = new CompositeSpecification();
        $newComposite = $composite->withRaw(condition: 'price > :min', params: ['min' => 30]);

        $specifications = $newComposite->getSpecifications();
        $this->assertCount(1, $specifications);
        $this->assertInstanceOf(RawSpecification::class, $specifications[0]);
        $this->assertSame('price > :min', $specifications[0]->getCondition());
        $this->assertSame(['min' => 30], $specifications[0]->getParams());
    }
}
