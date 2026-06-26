<?php

declare(strict_types=1);

namespace Rasuvaeff\Specification\Tests;

use Rasuvaeff\Specification\ComparisonSpecification;
use Rasuvaeff\Specification\NotSpecification;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;

#[Test]
#[Covers(NotSpecification::class)]
final class NotSpecificationTest
{
    public function constructorAndGetter(): void
    {
        $inner = new FakeSpecification();
        $spec = new NotSpecification(specification: $inner);

        Assert::same($spec->getSpecification(), $inner);
    }

    public function createFactoryMethod(): void
    {
        $inner = new FakeSpecification();
        $spec = NotSpecification::create(specification: $inner);

        Assert::instanceOf($spec, NotSpecification::class);
        Assert::same($spec->getSpecification(), $inner);
    }

    public function acceptsVisitor(): void
    {
        $inner = new FakeSpecification();
        $spec = new NotSpecification(specification: $inner);
        $visitor = new FakeVisitor(returnValue: 'result');

        $result = $spec->accept(visitor: $visitor);

        Assert::same($visitor->lastMethod, 'visitNot');
        Assert::same($visitor->lastArg, $spec);
        Assert::same($result, 'result');
    }

    public function withComparisonSpecification(): void
    {
        $inner = new ComparisonSpecification(column: 'status', value: 'active');
        $spec = new NotSpecification(specification: $inner);

        Assert::same($spec->getSpecification(), $inner);
    }
}
