<?php

declare(strict_types=1);

namespace Rasuvaeff\Specification\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Rasuvaeff\Specification\CompositeSpecification;
use Rasuvaeff\Specification\OffsetSpecification;

#[CoversClass(OffsetSpecification::class)]
final class OffsetSpecificationTest extends TestCase
{
    #[Test]
    public function constructionWithValidOffset(): void
    {
        $spec = new OffsetSpecification(offset: 10);

        $this->assertSame(10, $spec->getOffset());
    }

    #[Test]
    public function constructionWithZeroOffset(): void
    {
        $spec = new OffsetSpecification(offset: 0);

        $this->assertSame(0, $spec->getOffset());
    }

    #[Test]
    public function constructionWithNegativeOffsetThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Offset must be non-negative.');

        new OffsetSpecification(offset: -1);
    }

    #[Test]
    public function compositeWithOffset(): void
    {
        $composite = CompositeSpecification::create()
            ->withOffset(20);

        $specs = $composite->getSpecifications();
        $this->assertCount(1, $specs);
        $this->assertInstanceOf(OffsetSpecification::class, $specs[0]);
        $this->assertSame(20, $specs[0]->getOffset());
    }
}
