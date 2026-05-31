<?php

declare(strict_types=1);

namespace Rasuvaeff\Specification\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Rasuvaeff\Specification\LimitSpecification;

#[CoversClass(LimitSpecification::class)]
final class LimitSpecificationTest extends TestCase
{
    #[Test]
    public function constructionWithValidLimit(): void
    {
        $spec = new LimitSpecification(limit: 50);

        $this->assertSame(50, $spec->getLimit());
    }

    #[Test]
    public function constructionWithZeroLimit(): void
    {
        $spec = new LimitSpecification(limit: 0);

        $this->assertSame(0, $spec->getLimit());
    }

    #[Test]
    public function constructionWithNegativeLimitThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Limit must be non-negative.');

        new LimitSpecification(limit: -1);
    }
}
