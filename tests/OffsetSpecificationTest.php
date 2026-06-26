<?php

declare(strict_types=1);

namespace Rasuvaeff\Specification\Tests;

use Rasuvaeff\Specification\CompositeSpecification;
use Rasuvaeff\Specification\OffsetSpecification;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Expect;
use Testo\Test;

#[Test]
#[Covers(OffsetSpecification::class)]
final class OffsetSpecificationTest
{
    public function constructionWithValidOffset(): void
    {
        $spec = new OffsetSpecification(offset: 10);

        Assert::same($spec->getOffset(), 10);
    }

    public function constructionWithZeroOffset(): void
    {
        $spec = new OffsetSpecification(offset: 0);

        Assert::same($spec->getOffset(), 0);
    }

    public function constructionWithNegativeOffsetThrows(): void
    {
        Expect::exception(\InvalidArgumentException::class)->withMessageContaining('Offset must be non-negative');

        new OffsetSpecification(offset: -1);
    }

    public function compositeWithOffset(): void
    {
        $composite = CompositeSpecification::create()->withOffset(20);

        $specs = $composite->getSpecifications();
        Assert::count($specs, 1);
        $offset = $specs[0];
        Assert::instanceOf($offset, OffsetSpecification::class);
        Assert::same($offset->getOffset(), 20);
    }
}
