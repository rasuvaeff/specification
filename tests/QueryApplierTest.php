<?php

declare(strict_types=1);

namespace Rasuvaeff\Specification\Tests;

use Rasuvaeff\Specification\ComparisonSpecification;
use Rasuvaeff\Specification\QueryApplier;
use ReflectionClass;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;
use Yiisoft\Db\Query\Query;

#[Test]
#[Covers(QueryApplier::class)]
final class QueryApplierTest
{
    private function makeQuery(): Query
    {
        return new Query(db: new FakeConnection());
    }

    public function applyWithComparisonSpecification(): void
    {
        $query = $this->makeQuery();
        $spec = new ComparisonSpecification(column: 'status', value: 'active');

        QueryApplier::apply(specification: $spec, query: $query);

        Assert::same($query->getWhere(), ['=', 'status', 'active']);
    }

    public function applyCreatesVisitor(): void
    {
        $query = $this->makeQuery();
        $spec = new ComparisonSpecification(column: 'age', value: 25, operator: '>');

        QueryApplier::apply(specification: $spec, query: $query);

        Assert::same($query->getWhere(), ['>', 'age', 25]);
    }

    public function staticMethod(): void
    {
        $reflection = new ReflectionClass(objectOrClass: QueryApplier::class);
        $methods = $reflection->getMethods();

        $staticMethods = array_filter(
            array: $methods,
            callback: static fn(\ReflectionMethod $m): bool => $m->isStatic() && $m->isPublic(),
        );
        Assert::count($staticMethods, 1);
        Assert::same(array_values($staticMethods)[0]->getName(), 'apply');
    }
}
