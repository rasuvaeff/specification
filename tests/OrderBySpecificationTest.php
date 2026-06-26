<?php

declare(strict_types=1);

namespace Rasuvaeff\Specification\Tests;

use Rasuvaeff\Specification\OrderBySpecification;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Expect;
use Testo\Test;

#[Test]
#[Covers(OrderBySpecification::class)]
final class OrderBySpecificationTest
{
    public function constructorAndFirstValues(): void
    {
        $spec = new OrderBySpecification(columns: ['created_at' => 'DESC']);

        Assert::same($spec->getFirstColumn(), 'created_at');
        Assert::same($spec->getFirstDirection(), 'DESC');
        Assert::same($spec->getColumns(), ['created_at' => 'DESC']);
    }

    public function constructorRejectsEmptyColumns(): void
    {
        Expect::exception(\InvalidArgumentException::class)->withMessageContaining('Order by specification requires at least one column');

        new OrderBySpecification(columns: []);
    }

    public function singleColumn(): void
    {
        $spec = new OrderBySpecification(columns: ['name' => 'ASC']);

        Assert::same($spec->getFirstColumn(), 'name');
        Assert::same($spec->getFirstDirection(), 'ASC');
        Assert::same($spec->getColumns(), ['name' => 'ASC']);
    }

    public function multipleColumns(): void
    {
        $spec = new OrderBySpecification(columns: ['sort' => 'DESC', 'name' => 'ASC']);

        Assert::same($spec->getFirstColumn(), 'sort');
        Assert::same($spec->getFirstDirection(), 'DESC');
        Assert::same($spec->getColumns(), ['sort' => 'DESC', 'name' => 'ASC']);
    }

    public function threeColumns(): void
    {
        $spec = new OrderBySpecification(columns: ['priority' => 'DESC', 'sort' => 'ASC', 'name' => 'ASC']);

        Assert::same($spec->getFirstColumn(), 'priority');
        Assert::same($spec->getFirstDirection(), 'DESC');
        Assert::same($spec->getColumns(), ['priority' => 'DESC', 'sort' => 'ASC', 'name' => 'ASC']);
    }
}
