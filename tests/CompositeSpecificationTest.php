<?php

declare(strict_types=1);

namespace Rasuvaeff\Specification\Tests;

use Rasuvaeff\Specification\ComparisonSpecification;
use Rasuvaeff\Specification\CompositeSpecification;
use Rasuvaeff\Specification\LimitSpecification;
use Rasuvaeff\Specification\NotSpecification;
use Rasuvaeff\Specification\OrConditionSpecification;
use Rasuvaeff\Specification\OrderBySpecification;
use Rasuvaeff\Specification\RawSpecification;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;

#[Test]
#[Covers(CompositeSpecification::class)]
final class CompositeSpecificationTest
{
    public function emptyConstructor(): void
    {
        $spec = new CompositeSpecification();

        Assert::blank($spec->getSpecifications());
    }

    public function constructorWithSpecifications(): void
    {
        $spec1 = new FakeSpecification();
        $spec2 = new FakeSpecification();

        $spec = new CompositeSpecification(specifications: [$spec1, $spec2]);

        $specifications = $spec->getSpecifications();
        Assert::count($specifications, 2);
        Assert::same($specifications[0], $spec1);
        Assert::same($specifications[1], $spec2);
    }

    public function withSpecification(): void
    {
        $spec1 = new FakeSpecification();
        $spec2 = new FakeSpecification();

        $composite = new CompositeSpecification(specifications: [$spec1]);
        $newComposite = $composite->withSpecification(specification: $spec2);

        Assert::count($composite->getSpecifications(), 1);

        $specifications = $newComposite->getSpecifications();
        Assert::count($specifications, 2);
        Assert::same($specifications[0], $spec1);
        Assert::same($specifications[1], $spec2);
    }

    public function withOrCondition(): void
    {
        $composite = new CompositeSpecification();
        $newComposite = $composite->withOrCondition(conditions: [
            'status' => 'active',
            'type' => 'email',
        ]);

        $specifications = $newComposite->getSpecifications();
        Assert::count($specifications, 1);
        Assert::instanceOf($specifications[0], OrConditionSpecification::class);
    }

    public function withNot(): void
    {
        $inner = new FakeSpecification();
        $composite = new CompositeSpecification();

        $newComposite = $composite->withNot(specification: $inner);

        $specifications = $newComposite->getSpecifications();
        Assert::count($specifications, 1);
        Assert::instanceOf($specifications[0], NotSpecification::class);
    }

    public function create(): void
    {
        $spec = CompositeSpecification::create();

        Assert::instanceOf($spec, CompositeSpecification::class);
        Assert::blank($spec->getSpecifications());
    }

    public function withComparison(): void
    {
        $composite = new CompositeSpecification();
        $newComposite = $composite->withComparison(column: 'status', value: 'active');

        $specifications = $newComposite->getSpecifications();
        Assert::count($specifications, 1);
        Assert::instanceOf($specifications[0], ComparisonSpecification::class);
    }

    public function withSpecificationAppendsSpec(): void
    {
        $spec = new FakeSpecification();
        $composite = new CompositeSpecification();

        $newComposite = $composite->withSpecification(specification: $spec);

        $specifications = $newComposite->getSpecifications();
        Assert::count($specifications, 1);
        Assert::same($specifications[0], $spec);
    }

    public function withOrderBy(): void
    {
        $composite = new CompositeSpecification();
        $newComposite = $composite->withOrderBy(columns: ['created_at' => 'DESC']);

        $specifications = $newComposite->getSpecifications();
        Assert::count($specifications, 1);
        Assert::instanceOf($specifications[0], OrderBySpecification::class);
    }

    public function withLimit(): void
    {
        $composite = new CompositeSpecification();
        $newComposite = $composite->withLimit(limit: 10);

        $specifications = $newComposite->getSpecifications();
        Assert::count($specifications, 1);
        Assert::instanceOf($specifications[0], LimitSpecification::class);
    }

    public function acceptsVisitor(): void
    {
        $spec1 = new FakeSpecification();
        $spec2 = new FakeSpecification();

        $composite = new CompositeSpecification(specifications: [$spec1, $spec2]);
        $visitor = new FakeVisitor(returnValue: 'result');

        $result = $composite->accept(visitor: $visitor);

        Assert::same($visitor->lastMethod, 'visitComposite');
        Assert::same($visitor->lastArg, $composite);
        Assert::same($result, 'result');
    }

    public function withRaw(): void
    {
        $composite = new CompositeSpecification();
        $newComposite = $composite->withRaw(condition: 'price > :min', params: ['min' => 30]);

        $specifications = $newComposite->getSpecifications();
        Assert::count($specifications, 1);
        $raw = $specifications[0];
        Assert::instanceOf($raw, RawSpecification::class);
        Assert::same($raw->getCondition(), 'price > :min');
        Assert::same($raw->getParams(), ['min' => 30]);
    }
}
