<?php

declare(strict_types=1);

namespace Rasuvaeff\Specification\Tests;

use Rasuvaeff\Specification\LimitSpecification;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Expect;
use Testo\Test;

#[Test]
#[Covers(LimitSpecification::class)]
final class LimitSpecificationTest
{
    public function constructionWithValidLimit(): void
    {
        $spec = new LimitSpecification(limit: 50);

        Assert::same($spec->getLimit(), 50);
    }

    public function constructionWithZeroLimit(): void
    {
        $spec = new LimitSpecification(limit: 0);

        Assert::same($spec->getLimit(), 0);
    }

    public function constructionWithNegativeLimitThrows(): void
    {
        Expect::exception(\InvalidArgumentException::class)->withMessageContaining('Limit must be non-negative');

        new LimitSpecification(limit: -1);
    }
}
