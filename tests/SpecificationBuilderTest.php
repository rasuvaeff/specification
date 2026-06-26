<?php

declare(strict_types=1);

namespace Rasuvaeff\Specification\Tests;

use Rasuvaeff\Specification\ComparisonSpecification;
use Rasuvaeff\Specification\CompositeSpecification;
use Rasuvaeff\Specification\LimitSpecification;
use Rasuvaeff\Specification\NotSpecification;
use Rasuvaeff\Specification\OffsetSpecification;
use Rasuvaeff\Specification\OrderBySpecification;
use Rasuvaeff\Specification\OrSpecification;
use Rasuvaeff\Specification\SpecificationBuilder;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;

#[Test]
#[Covers(SpecificationBuilder::class)]
final class SpecificationBuilderTest
{
    public function create(): void
    {
        $builder = SpecificationBuilder::create();
        Assert::instanceOf($builder, SpecificationBuilder::class);

        $spec = $builder->build();
        Assert::instanceOf($spec, CompositeSpecification::class);
        Assert::blank($spec->getSpecifications());
    }

    public function where(): void
    {
        $builder = SpecificationBuilder::create()
            ->where(column: 'status', value: 'active');

        $specifications = $builder->build()->getSpecifications();
        Assert::count($specifications, 1);

        $spec = $specifications[0];
        Assert::instanceOf($spec, ComparisonSpecification::class);
        Assert::same($spec->getOperator(), '=');
    }

    public function whereWithCustomOperator(): void
    {
        $builder = SpecificationBuilder::create()
            ->where(column: 'age', value: 25, operator: '>');

        $specifications = $builder->build()->getSpecifications();
        $spec = $specifications[0];
        Assert::instanceOf($spec, ComparisonSpecification::class);
        Assert::same($spec->getOperator(), '>');
    }

    public function whereEqual(): void
    {
        $builder = SpecificationBuilder::create()
            ->whereEqual(column: 'status', value: 'active');

        $specifications = $builder->build()->getSpecifications();
        $spec = $specifications[0];
        Assert::instanceOf($spec, ComparisonSpecification::class);
        Assert::same($spec->getOperator(), '=');
    }

    public function whereNotEqual(): void
    {
        $builder = SpecificationBuilder::create()
            ->whereNotEqual(column: 'status', value: 'inactive');

        $specifications = $builder->build()->getSpecifications();
        $spec = $specifications[0];
        Assert::instanceOf($spec, ComparisonSpecification::class);
        Assert::same($spec->getOperator(), '!=');
    }

    public function whereGreaterThan(): void
    {
        $builder = SpecificationBuilder::create()
            ->whereGreaterThan(column: 'age', value: 18);

        $specifications = $builder->build()->getSpecifications();
        $spec = $specifications[0];
        Assert::instanceOf($spec, ComparisonSpecification::class);
        Assert::same($spec->getOperator(), '>');
    }

    public function whereLessThan(): void
    {
        $builder = SpecificationBuilder::create()
            ->whereLessThan(column: 'age', value: 65);

        $specifications = $builder->build()->getSpecifications();
        $spec = $specifications[0];
        Assert::instanceOf($spec, ComparisonSpecification::class);
        Assert::same($spec->getOperator(), '<');
    }

    public function whereGreaterThanOrEqual(): void
    {
        $builder = SpecificationBuilder::create()
            ->whereGreaterThanOrEqual(column: 'score', value: 100);

        $specifications = $builder->build()->getSpecifications();
        $spec = $specifications[0];
        Assert::instanceOf($spec, ComparisonSpecification::class);
        Assert::same($spec->getOperator(), '>=');
    }

    public function whereLessThanOrEqual(): void
    {
        $builder = SpecificationBuilder::create()
            ->whereLessThanOrEqual(column: 'score', value: 100);

        $specifications = $builder->build()->getSpecifications();
        $spec = $specifications[0];
        Assert::instanceOf($spec, ComparisonSpecification::class);
        Assert::same($spec->getOperator(), '<=');
    }

    public function whereIn(): void
    {
        $builder = SpecificationBuilder::create()
            ->whereIn(column: 'status', values: ['active', 'pending']);

        $specifications = $builder->build()->getSpecifications();
        $spec = $specifications[0];
        Assert::instanceOf($spec, ComparisonSpecification::class);
        Assert::same($spec->getOperator(), 'in');
        Assert::same($spec->getValue(), ['active', 'pending']);
    }

    public function whereNotIn(): void
    {
        $builder = SpecificationBuilder::create()
            ->whereNotIn(column: 'status', values: ['deleted', 'archived']);

        $specifications = $builder->build()->getSpecifications();
        $spec = $specifications[0];
        Assert::instanceOf($spec, ComparisonSpecification::class);
        Assert::same($spec->getOperator(), 'not in');
    }

    public function whereLike(): void
    {
        $builder = SpecificationBuilder::create()
            ->whereLike(column: 'name', pattern: '%john%');

        $specifications = $builder->build()->getSpecifications();
        $spec = $specifications[0];
        Assert::instanceOf($spec, ComparisonSpecification::class);
        Assert::same($spec->getOperator(), 'like');
    }

    public function whereNotLike(): void
    {
        $builder = SpecificationBuilder::create()
            ->whereNotLike(column: 'name', pattern: '%admin%');

        $specifications = $builder->build()->getSpecifications();
        $spec = $specifications[0];
        Assert::instanceOf($spec, ComparisonSpecification::class);
        Assert::same($spec->getOperator(), 'not like');
    }

    public function whereBetween(): void
    {
        $builder = SpecificationBuilder::create()
            ->whereBetween(column: 'age', from: 18, to: 65);

        $specifications = $builder->build()->getSpecifications();
        $spec = $specifications[0];
        Assert::instanceOf($spec, ComparisonSpecification::class);
        Assert::same($spec->getOperator(), 'between');
        Assert::same($spec->getValue(), [18, 65]);
    }

    public function whereNull(): void
    {
        $builder = SpecificationBuilder::create()
            ->whereNull(column: 'deleted_at');

        $specifications = $builder->build()->getSpecifications();
        $spec = $specifications[0];
        Assert::instanceOf($spec, ComparisonSpecification::class);
        Assert::same($spec->getOperator(), 'is');
        Assert::null($spec->getValue());
    }

    public function whereNotNull(): void
    {
        $builder = SpecificationBuilder::create()
            ->whereNotNull(column: 'deleted_at');

        $specifications = $builder->build()->getSpecifications();
        $spec = $specifications[0];
        Assert::instanceOf($spec, ComparisonSpecification::class);
        Assert::same($spec->getOperator(), 'is not');
    }

    public function chainedMethods(): void
    {
        $builder = SpecificationBuilder::create()
            ->whereEqual(column: 'status', value: 'active')
            ->whereGreaterThan(column: 'age', value: 18)
            ->whereLike(column: 'name', pattern: '%john%');

        $specifications = $builder->build()->getSpecifications();
        Assert::count($specifications, 3);

        $spec0 = $specifications[0];
        $spec1 = $specifications[1];
        $spec2 = $specifications[2];
        Assert::instanceOf($spec0, ComparisonSpecification::class);
        Assert::instanceOf($spec1, ComparisonSpecification::class);
        Assert::instanceOf($spec2, ComparisonSpecification::class);
        Assert::same($spec0->getOperator(), '=');
        Assert::same($spec1->getOperator(), '>');
        Assert::same($spec2->getOperator(), 'like');
    }

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
        Assert::count($specifications, 6);

        Assert::same($specifications[0]->getOperator(), 'not between');
        Assert::same($specifications[1]->getOperator(), 'ilike');
        Assert::same($specifications[2]->getOperator(), 'not ilike');
        Assert::same($specifications[3]->getOperator(), 'like');
        Assert::same($specifications[3]->getValue(), 'pre-%');
        Assert::same($specifications[4]->getOperator(), 'like');
        Assert::same($specifications[4]->getValue(), '%-post');
        Assert::same($specifications[5]->getOperator(), 'like');
        Assert::same($specifications[5]->getValue(), '%mid%');
    }

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
        Assert::count($specifications, 1);
        /** @var OrSpecification $flatOr */
        $flatOr = $specifications[0];
        Assert::instanceOf($flatOr, OrSpecification::class);
        Assert::count($flatOr->getSpecifications(), 3);
    }

    public function orWhere(): void
    {
        $builder = SpecificationBuilder::create()
            ->whereEqual(column: 'status', value: 'active')
            ->orWhere(callback: function (SpecificationBuilder $builder): void {
                $builder->whereEqual(column: 'type', value: 'email')
                    ->whereGreaterThan(column: 'priority', value: 5);
            });

        $specifications = $builder->build()->getSpecifications();
        Assert::count($specifications, 1);
        Assert::instanceOf($specifications[0], OrSpecification::class);

        /** @var OrSpecification $orSpecification */
        $orSpecification = $specifications[0];
        $orSpecifications = $orSpecification->getSpecifications();
        Assert::count($orSpecifications, 2);
        /** @var CompositeSpecification $orLeft */
        $orLeft = $orSpecifications[0];
        /** @var CompositeSpecification $orRight */
        $orRight = $orSpecifications[1];
        Assert::instanceOf($orLeft, CompositeSpecification::class);
        Assert::instanceOf($orRight, CompositeSpecification::class);
        Assert::count($orLeft->getSpecifications(), 1);
        Assert::count($orRight->getSpecifications(), 2);
    }

    public function notWhere(): void
    {
        $builder = SpecificationBuilder::create()
            ->whereEqual(column: 'status', value: 'active')
            ->notWhere(callback: function (SpecificationBuilder $builder): void {
                $builder->whereEqual(column: 'type', value: 'spam');
            });

        $specifications = $builder->build()->getSpecifications();
        Assert::count($specifications, 2);
        Assert::instanceOf($specifications[0], ComparisonSpecification::class);
        Assert::instanceOf($specifications[1], NotSpecification::class);

        /** @var NotSpecification $notSpecification */
        $notSpecification = $specifications[1];
        $nestedSpecification = $notSpecification->getSpecification();
        Assert::instanceOf($nestedSpecification, CompositeSpecification::class);
        /** @var CompositeSpecification $nestedSpecification */
        Assert::count($nestedSpecification->getSpecifications(), 1);
    }

    public function orderBy(): void
    {
        $builder = SpecificationBuilder::create()
            ->whereEqual(column: 'status', value: 'active')
            ->orderBy(columns: ['created_at' => 'DESC']);

        $specifications = $builder->build()->getSpecifications();
        Assert::count($specifications, 2);
        Assert::instanceOf($specifications[0], ComparisonSpecification::class);
        Assert::instanceOf($specifications[1], OrderBySpecification::class);
    }

    public function limit(): void
    {
        $builder = SpecificationBuilder::create()
            ->limit(limit: 10);

        $specifications = $builder->build()->getSpecifications();
        Assert::count($specifications, 1);
        $limitSpec = $specifications[0];
        Assert::instanceOf($limitSpec, LimitSpecification::class);
        Assert::same($limitSpec->getLimit(), 10);
    }

    public function offset(): void
    {
        $builder = SpecificationBuilder::create()
            ->offset(offset: 20);

        $specifications = $builder->build()->getSpecifications();
        Assert::count($specifications, 1);
        $offsetSpec = $specifications[0];
        Assert::instanceOf($offsetSpec, OffsetSpecification::class);
        Assert::same($offsetSpec->getOffset(), 20);
    }

    public function fullPaginationChain(): void
    {
        $builder = SpecificationBuilder::create()
            ->whereEqual(column: 'status', value: 'active')
            ->orderBy(columns: ['id' => 'ASC'])
            ->limit(limit: 20)
            ->offset(offset: 40);

        $specifications = $builder->build()->getSpecifications();
        Assert::count($specifications, 4);
    }

    public function whereIsImmutableDoesNotModifyOriginal(): void
    {
        $original = SpecificationBuilder::create();
        $original->whereEqual(column: 'status', value: 'active');

        Assert::blank($original->build()->getSpecifications());
    }

    public function orderByIsImmutableDoesNotModifyOriginal(): void
    {
        $original = SpecificationBuilder::create();
        $original->orderBy(columns: ['x' => 'ASC']);

        Assert::blank($original->build()->getSpecifications());
    }

    public function limitIsImmutableDoesNotModifyOriginal(): void
    {
        $original = SpecificationBuilder::create();
        $original->limit(limit: 10);

        Assert::blank($original->build()->getSpecifications());
    }

    public function offsetIsImmutableDoesNotModifyOriginal(): void
    {
        $original = SpecificationBuilder::create();
        $original->offset(offset: 5);

        Assert::blank($original->build()->getSpecifications());
    }

    public function orWhereDoesNotModifyOriginal(): void
    {
        $original = SpecificationBuilder::create()
            ->whereEqual(column: 'status', value: 'active');

        $originalSpecs = $original->build()->getSpecifications();
        Assert::count($originalSpecs, 1);

        $modified = $original->orWhere(callback: function (SpecificationBuilder $builder): void {
            $builder->whereEqual(column: 'type', value: 'email');
        });

        $originalSpecsAfter = $original->build()->getSpecifications();
        Assert::count($originalSpecsAfter, 1);
        $spec = $originalSpecsAfter[0];
        Assert::instanceOf($spec, ComparisonSpecification::class);
        Assert::same($spec->getValue(), 'active');

        $modifiedSpecs = $modified->build()->getSpecifications();
        /** @var OrSpecification $modifiedOr */
        $modifiedOr = $modifiedSpecs[0];
        Assert::instanceOf($modifiedOr, OrSpecification::class);
        Assert::count($modifiedOr->getSpecifications(), 2);
    }

    public function notWhereDoesNotModifyOriginal(): void
    {
        $original = SpecificationBuilder::create()
            ->whereEqual(column: 'status', value: 'active');

        $original->notWhere(callback: function (SpecificationBuilder $builder): void {
            $builder->whereEqual(column: 'type', value: 'spam');
        });

        $originalSpecs = $original->build()->getSpecifications();
        Assert::count($originalSpecs, 1);
    }
}
