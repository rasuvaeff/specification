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
use Rasuvaeff\Specification\OffsetSpecification;
use Rasuvaeff\Specification\OrderBySpecification;
use Rasuvaeff\Specification\OrSpecification;
use Rasuvaeff\Specification\SpecificationBuilder;

#[CoversClass(SpecificationBuilder::class)]
final class SpecificationBuilderTest extends TestCase
{
    #[Test]
    public function create(): void
    {
        $builder = SpecificationBuilder::create();
        $this->assertInstanceOf(SpecificationBuilder::class, $builder);

        $spec = $builder->build();
        $this->assertInstanceOf(CompositeSpecification::class, $spec);
        $this->assertEmpty($spec->getSpecifications());
    }

    #[Test]
    public function where(): void
    {
        $builder = SpecificationBuilder::create()
            ->where(column: 'status', value: 'active');

        $specifications = $builder->build()->getSpecifications();
        $this->assertCount(1, $specifications);

        $spec = $specifications[0];
        $this->assertInstanceOf(ComparisonSpecification::class, $spec);
        $this->assertSame('=', $spec->getOperator());
    }

    #[Test]
    public function whereWithCustomOperator(): void
    {
        $builder = SpecificationBuilder::create()
            ->where(column: 'age', value: 25, operator: '>');

        $specifications = $builder->build()->getSpecifications();
        $spec = $specifications[0];
        $this->assertInstanceOf(ComparisonSpecification::class, $spec);
        $this->assertSame('>', $spec->getOperator());
    }

    #[Test]
    public function whereEqual(): void
    {
        $builder = SpecificationBuilder::create()
            ->whereEqual(column: 'status', value: 'active');

        $specifications = $builder->build()->getSpecifications();
        $spec = $specifications[0];
        $this->assertInstanceOf(ComparisonSpecification::class, $spec);
        $this->assertSame('=', $spec->getOperator());
    }

    #[Test]
    public function whereNotEqual(): void
    {
        $builder = SpecificationBuilder::create()
            ->whereNotEqual(column: 'status', value: 'inactive');

        $specifications = $builder->build()->getSpecifications();
        $spec = $specifications[0];
        $this->assertInstanceOf(ComparisonSpecification::class, $spec);
        $this->assertSame('!=', $spec->getOperator());
    }

    #[Test]
    public function whereGreaterThan(): void
    {
        $builder = SpecificationBuilder::create()
            ->whereGreaterThan(column: 'age', value: 18);

        $specifications = $builder->build()->getSpecifications();
        $spec = $specifications[0];
        $this->assertInstanceOf(ComparisonSpecification::class, $spec);
        $this->assertSame('>', $spec->getOperator());
    }

    #[Test]
    public function whereLessThan(): void
    {
        $builder = SpecificationBuilder::create()
            ->whereLessThan(column: 'age', value: 65);

        $specifications = $builder->build()->getSpecifications();
        $spec = $specifications[0];
        $this->assertInstanceOf(ComparisonSpecification::class, $spec);
        $this->assertSame('<', $spec->getOperator());
    }

    #[Test]
    public function whereGreaterThanOrEqual(): void
    {
        $builder = SpecificationBuilder::create()
            ->whereGreaterThanOrEqual(column: 'score', value: 100);

        $specifications = $builder->build()->getSpecifications();
        $spec = $specifications[0];
        $this->assertInstanceOf(ComparisonSpecification::class, $spec);
        $this->assertSame('>=', $spec->getOperator());
    }

    #[Test]
    public function whereLessThanOrEqual(): void
    {
        $builder = SpecificationBuilder::create()
            ->whereLessThanOrEqual(column: 'score', value: 100);

        $specifications = $builder->build()->getSpecifications();
        $spec = $specifications[0];
        $this->assertInstanceOf(ComparisonSpecification::class, $spec);
        $this->assertSame('<=', $spec->getOperator());
    }

    #[Test]
    public function whereIn(): void
    {
        $builder = SpecificationBuilder::create()
            ->whereIn(column: 'status', values: ['active', 'pending']);

        $specifications = $builder->build()->getSpecifications();
        $spec = $specifications[0];
        $this->assertInstanceOf(ComparisonSpecification::class, $spec);
        $this->assertSame('in', $spec->getOperator());
        $this->assertSame(['active', 'pending'], $spec->getValue());
    }

    #[Test]
    public function whereNotIn(): void
    {
        $builder = SpecificationBuilder::create()
            ->whereNotIn(column: 'status', values: ['deleted', 'archived']);

        $specifications = $builder->build()->getSpecifications();
        $spec = $specifications[0];
        $this->assertInstanceOf(ComparisonSpecification::class, $spec);
        $this->assertSame('not in', $spec->getOperator());
    }

    #[Test]
    public function whereLike(): void
    {
        $builder = SpecificationBuilder::create()
            ->whereLike(column: 'name', pattern: '%john%');

        $specifications = $builder->build()->getSpecifications();
        $spec = $specifications[0];
        $this->assertInstanceOf(ComparisonSpecification::class, $spec);
        $this->assertSame('like', $spec->getOperator());
    }

    #[Test]
    public function whereNotLike(): void
    {
        $builder = SpecificationBuilder::create()
            ->whereNotLike(column: 'name', pattern: '%admin%');

        $specifications = $builder->build()->getSpecifications();
        $spec = $specifications[0];
        $this->assertInstanceOf(ComparisonSpecification::class, $spec);
        $this->assertSame('not like', $spec->getOperator());
    }

    #[Test]
    public function whereBetween(): void
    {
        $builder = SpecificationBuilder::create()
            ->whereBetween(column: 'age', from: 18, to: 65);

        $specifications = $builder->build()->getSpecifications();
        $spec = $specifications[0];
        $this->assertInstanceOf(ComparisonSpecification::class, $spec);
        $this->assertSame('between', $spec->getOperator());
        $this->assertSame([18, 65], $spec->getValue());
    }

    #[Test]
    public function whereNull(): void
    {
        $builder = SpecificationBuilder::create()
            ->whereNull(column: 'deleted_at');

        $specifications = $builder->build()->getSpecifications();
        $spec = $specifications[0];
        $this->assertInstanceOf(ComparisonSpecification::class, $spec);
        $this->assertSame('is', $spec->getOperator());
        $this->assertNull($spec->getValue());
    }

    #[Test]
    public function whereNotNull(): void
    {
        $builder = SpecificationBuilder::create()
            ->whereNotNull(column: 'deleted_at');

        $specifications = $builder->build()->getSpecifications();
        $spec = $specifications[0];
        $this->assertInstanceOf(ComparisonSpecification::class, $spec);
        $this->assertSame('is not', $spec->getOperator());
    }

    #[Test]
    public function chainedMethods(): void
    {
        $builder = SpecificationBuilder::create()
            ->whereEqual(column: 'status', value: 'active')
            ->whereGreaterThan(column: 'age', value: 18)
            ->whereLike(column: 'name', pattern: '%john%');

        $specifications = $builder->build()->getSpecifications();
        $this->assertCount(3, $specifications);

        $spec0 = $specifications[0];
        $spec1 = $specifications[1];
        $spec2 = $specifications[2];
        $this->assertInstanceOf(ComparisonSpecification::class, $spec0);
        $this->assertInstanceOf(ComparisonSpecification::class, $spec1);
        $this->assertInstanceOf(ComparisonSpecification::class, $spec2);
        $this->assertSame('=', $spec0->getOperator());
        $this->assertSame('>', $spec1->getOperator());
        $this->assertSame('like', $spec2->getOperator());
    }

    #[Test]
    public function whereNotBetweenAndStringShortcuts(): void
    {
        $builder = SpecificationBuilder::create()
            ->whereNotBetween(column: 'age', from: 18, to: 65)
            ->whereIlike(column: 'name', pattern: '%john%')
            ->whereNotIlike(column: 'email', pattern: '%@spam.com')
            ->whereStartsWith(column: 'slug', prefix: 'pre-')
            ->whereEndsWith(column: 'slug', suffix: '-post')
            ->whereContains(column: 'slug', substring: 'mid');

        /** @var list<ComparisonSpecification> $specifications */
        $specifications = $builder->build()->getSpecifications();
        $this->assertCount(6, $specifications);

        $this->assertSame('not between', $specifications[0]->getOperator());
        $this->assertSame('ilike', $specifications[1]->getOperator());
        $this->assertSame('not ilike', $specifications[2]->getOperator());
        $this->assertSame('like', $specifications[3]->getOperator());
        $this->assertSame('pre-%', $specifications[3]->getValue());
        $this->assertSame('like', $specifications[4]->getOperator());
        $this->assertSame('%-post', $specifications[4]->getValue());
        $this->assertSame('like', $specifications[5]->getOperator());
        $this->assertSame('%mid%', $specifications[5]->getValue());
    }

    #[Test]
    public function sequentialOrWhereFlattens(): void
    {
        $builder = SpecificationBuilder::create()
            ->whereEqual(column: 'status', value: 'active')
            ->orWhere(callback: function (SpecificationBuilder $builder): void {
                $builder->whereEqual(column: 'type', value: 'email');
            })
            ->orWhere(callback: function (SpecificationBuilder $builder): void {
                $builder->whereEqual(column: 'priority', value: 5);
            });

        $specifications = $builder->build()->getSpecifications();
        $this->assertCount(1, $specifications);
        $this->assertInstanceOf(OrSpecification::class, $specifications[0]);
        $this->assertCount(3, $specifications[0]->getSpecifications());
    }

    #[Test]
    public function orWhere(): void
    {
        $builder = SpecificationBuilder::create()
            ->whereEqual(column: 'status', value: 'active')
            ->orWhere(callback: function (SpecificationBuilder $builder): void {
                $builder->whereEqual(column: 'type', value: 'email')
                    ->whereGreaterThan(column: 'priority', value: 5);
            });

        $specifications = $builder->build()->getSpecifications();
        $this->assertCount(1, $specifications);
        $this->assertInstanceOf(OrSpecification::class, $specifications[0]);

        $orSpecification = $specifications[0];
        $orSpecifications = $orSpecification->getSpecifications();
        $this->assertCount(2, $orSpecifications);
        $this->assertInstanceOf(CompositeSpecification::class, $orSpecifications[0]);
        $this->assertInstanceOf(CompositeSpecification::class, $orSpecifications[1]);
        $this->assertCount(1, $orSpecifications[0]->getSpecifications());
        $this->assertCount(2, $orSpecifications[1]->getSpecifications());
    }

    #[Test]
    public function notWhere(): void
    {
        $builder = SpecificationBuilder::create()
            ->whereEqual(column: 'status', value: 'active')
            ->notWhere(callback: function (SpecificationBuilder $builder): void {
                $builder->whereEqual(column: 'type', value: 'spam');
            });

        $specifications = $builder->build()->getSpecifications();
        $this->assertCount(2, $specifications);
        $this->assertInstanceOf(ComparisonSpecification::class, $specifications[0]);
        $this->assertInstanceOf(NotSpecification::class, $specifications[1]);

        $notSpecification = $specifications[1];
        $nestedSpecification = $notSpecification->getSpecification();
        $this->assertInstanceOf(CompositeSpecification::class, $nestedSpecification);
        $this->assertCount(1, $nestedSpecification->getSpecifications());
    }

    #[Test]
    public function orderBy(): void
    {
        $builder = SpecificationBuilder::create()
            ->whereEqual(column: 'status', value: 'active')
            ->orderBy(columns: ['created_at' => 'DESC']);

        $specifications = $builder->build()->getSpecifications();
        $this->assertCount(2, $specifications);
        $this->assertInstanceOf(ComparisonSpecification::class, $specifications[0]);
        $this->assertInstanceOf(OrderBySpecification::class, $specifications[1]);
    }

    #[Test]
    public function limit(): void
    {
        $builder = SpecificationBuilder::create()
            ->limit(limit: 10);

        $specifications = $builder->build()->getSpecifications();
        $this->assertCount(1, $specifications);
        $this->assertInstanceOf(LimitSpecification::class, $specifications[0]);
        $this->assertSame(10, $specifications[0]->getLimit());
    }

    #[Test]
    public function offset(): void
    {
        $builder = SpecificationBuilder::create()
            ->offset(offset: 20);

        $specifications = $builder->build()->getSpecifications();
        $this->assertCount(1, $specifications);
        $this->assertInstanceOf(OffsetSpecification::class, $specifications[0]);
        $this->assertSame(20, $specifications[0]->getOffset());
    }

    #[Test]
    public function fullPaginationChain(): void
    {
        $builder = SpecificationBuilder::create()
            ->whereEqual(column: 'status', value: 'active')
            ->orderBy(columns: ['id' => 'ASC'])
            ->limit(limit: 20)
            ->offset(offset: 40);

        $specifications = $builder->build()->getSpecifications();
        $this->assertCount(4, $specifications);
    }

    #[Test]
    public function whereIsImmutableDoesNotModifyOriginal(): void
    {
        $original = SpecificationBuilder::create();
        $original->whereEqual(column: 'status', value: 'active');

        $this->assertEmpty($original->build()->getSpecifications());
    }

    #[Test]
    public function orderByIsImmutableDoesNotModifyOriginal(): void
    {
        $original = SpecificationBuilder::create();
        $original->orderBy(columns: ['x' => 'ASC']);

        $this->assertEmpty($original->build()->getSpecifications());
    }

    #[Test]
    public function limitIsImmutableDoesNotModifyOriginal(): void
    {
        $original = SpecificationBuilder::create();
        $original->limit(limit: 10);

        $this->assertEmpty($original->build()->getSpecifications());
    }

    #[Test]
    public function offsetIsImmutableDoesNotModifyOriginal(): void
    {
        $original = SpecificationBuilder::create();
        $original->offset(offset: 5);

        $this->assertEmpty($original->build()->getSpecifications());
    }

    #[Test]
    public function orWhereDoesNotModifyOriginal(): void
    {
        $original = SpecificationBuilder::create()
            ->whereEqual(column: 'status', value: 'active');

        $original->orWhere(callback: function (SpecificationBuilder $builder): void {
            $builder->whereEqual(column: 'type', value: 'email');
        });

        $originalSpecs = $original->build()->getSpecifications();
        $this->assertCount(1, $originalSpecs);
    }

    #[Test]
    public function notWhereDoesNotModifyOriginal(): void
    {
        $original = SpecificationBuilder::create()
            ->whereEqual(column: 'status', value: 'active');

        $original->notWhere(callback: function (SpecificationBuilder $builder): void {
            $builder->whereEqual(column: 'type', value: 'spam');
        });

        $originalSpecs = $original->build()->getSpecifications();
        $this->assertCount(1, $originalSpecs);
    }
}
