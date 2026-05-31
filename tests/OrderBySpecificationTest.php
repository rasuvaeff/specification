<?php

declare(strict_types=1);

namespace Rasuvaeff\Specification\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Rasuvaeff\Specification\OrderBySpecification;

#[CoversClass(OrderBySpecification::class)]
final class OrderBySpecificationTest extends TestCase
{
    #[Test]
    public function constructorAndFirstValues(): void
    {
        $spec = new OrderBySpecification(columns: ['created_at' => 'DESC']);

        $this->assertSame('created_at', $spec->getFirstColumn());
        $this->assertSame('DESC', $spec->getFirstDirection());
        $this->assertSame(['created_at' => 'DESC'], $spec->getColumns());
    }

    #[Test]
    public function constructorRejectsEmptyColumns(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Order by specification requires at least one column');

        new OrderBySpecification(columns: []);
    }

    #[Test]
    public function singleColumn(): void
    {
        $spec = new OrderBySpecification(columns: ['name' => 'ASC']);

        $this->assertSame('name', $spec->getFirstColumn());
        $this->assertSame('ASC', $spec->getFirstDirection());
        $this->assertSame(['name' => 'ASC'], $spec->getColumns());
    }

    #[Test]
    public function multipleColumns(): void
    {
        $spec = new OrderBySpecification(columns: ['sort' => 'DESC', 'name' => 'ASC']);

        $this->assertSame('sort', $spec->getFirstColumn());
        $this->assertSame('DESC', $spec->getFirstDirection());
        $this->assertSame(['sort' => 'DESC', 'name' => 'ASC'], $spec->getColumns());
    }

    #[Test]
    public function threeColumns(): void
    {
        $spec = new OrderBySpecification(columns: ['priority' => 'DESC', 'sort' => 'ASC', 'name' => 'ASC']);

        $this->assertSame('priority', $spec->getFirstColumn());
        $this->assertSame('DESC', $spec->getFirstDirection());
        $this->assertSame(['priority' => 'DESC', 'sort' => 'ASC', 'name' => 'ASC'], $spec->getColumns());
    }
}
