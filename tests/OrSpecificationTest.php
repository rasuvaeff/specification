<?php

declare(strict_types=1);

namespace Rasuvaeff\Specification\Tests;

use Rasuvaeff\Specification\ComparisonSpecification;
use Rasuvaeff\Specification\OrSpecification;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;

#[Test]
#[Covers(OrSpecification::class)]
final class OrSpecificationTest
{
    public function emptyConstructor(): void
    {
        $spec = new OrSpecification();

        Assert::blank($spec->getSpecifications());
    }

    public function constructorWithSpecifications(): void
    {
        $spec1 = new ComparisonSpecification(column: 'status', value: 'active');
        $spec2 = new ComparisonSpecification(column: 'type', value: 'email');

        $spec = new OrSpecification(specifications: [$spec1, $spec2]);

        $specs = $spec->getSpecifications();
        Assert::count($specs, 2);
        Assert::same($specs[0], $spec1);
        Assert::same($specs[1], $spec2);
    }

    public function createFactoryMethod(): void
    {
        $spec1 = new FakeSpecification();
        $spec2 = new FakeSpecification();

        $spec = OrSpecification::create($spec1, $spec2);

        $specs = $spec->getSpecifications();
        Assert::count($specs, 2);
        Assert::same($specs[0], $spec1);
        Assert::same($specs[1], $spec2);
    }

    public function createWithNoArguments(): void
    {
        $spec = OrSpecification::create();

        Assert::blank($spec->getSpecifications());
    }

    public function acceptsVisitor(): void
    {
        $inner = new FakeSpecification();
        $spec = new OrSpecification(specifications: [$inner]);
        $visitor = new FakeVisitor(returnValue: 'result');

        $result = $spec->accept(visitor: $visitor);

        Assert::same($visitor->lastMethod, 'visitOr');
        Assert::same($visitor->lastArg, $spec);
        Assert::same($result, 'result');
    }

    public function acceptWithMultipleSpecifications(): void
    {
        $spec1 = new FakeSpecification();
        $spec2 = new FakeSpecification();
        $spec3 = new FakeSpecification();

        $spec = OrSpecification::create($spec1, $spec2, $spec3);

        $specs = $spec->getSpecifications();
        Assert::count($specs, 3);
        Assert::same($specs[0], $spec1);
        Assert::same($specs[1], $spec2);
        Assert::same($specs[2], $spec3);
    }
}
