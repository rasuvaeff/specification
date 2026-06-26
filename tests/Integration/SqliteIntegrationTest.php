<?php

declare(strict_types=1);

namespace Rasuvaeff\Specification\Tests\Integration;

use Rasuvaeff\Specification\CompositeSpecification;
use Rasuvaeff\Specification\NotSpecification;
use Rasuvaeff\Specification\OrSpecification;
use Rasuvaeff\Specification\QueryApplier;
use Rasuvaeff\Specification\SpecificationBuilder;
use Testo\Assert;
use Testo\Codecov\CoversNothing;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;
use Yiisoft\Cache\ArrayCache;
use Yiisoft\Db\Cache\SchemaCache;
use Yiisoft\Db\Query\Query;
use Yiisoft\Db\Sqlite\Connection;
use Yiisoft\Db\Sqlite\Driver;

/**
 * End-to-end tests that build and execute real SQL on an in-memory SQLite
 * database. Unlike the structural tests (which inspect getWhere()/getParams()),
 * these verify the generated SQL is correct and — critically — that parameter
 * placeholders in OR/NOT subqueries do not collide.
 */
#[Test]
#[CoversNothing]
final class SqliteIntegrationTest
{
    private Connection $db;

    #[BeforeTest]
    public function setUp(): void
    {
        $this->db = new Connection(new Driver('sqlite::memory:'), new SchemaCache(new ArrayCache()));

        $this->db->createCommand(
            'CREATE TABLE items (id INTEGER PRIMARY KEY, name TEXT, status TEXT, price INTEGER, created_at TEXT)',
        )->execute();

        $rows = [
            [1, 'alpha', 'active', 10, '2024-01-01'],
            [2, 'bravo', 'active', 20, '2024-02-01'],
            [3, 'charlie', 'inactive', 30, '2024-03-01'],
            [4, 'delta', 'active', 40, '2024-04-01'],
            [5, 'echo', 'inactive', 50, '2024-05-01'],
        ];
        $this->db->createCommand()
            ->insertBatch('items', $rows, ['id', 'name', 'status', 'price', 'created_at'])
            ->execute();
    }

    /**
     * @return list<int> Matching ids, sorted ascending.
     */
    private function ids(CompositeSpecification $spec): array
    {
        $query = (new Query($this->db))->select('id')->from('items');
        QueryApplier::apply(specification: $spec, query: $query);

        $ids = array_map($this->rowId(...), $query->all());
        sort($ids);

        return $ids;
    }

    private function rowId(array|object $row): int
    {
        $row = (array) $row;

        return (int) $row['id'];
    }

    public function simpleEquals(): void
    {
        $spec = SpecificationBuilder::create()->whereEqual(column: 'status', value: 'active')->build();

        Assert::same($this->ids($spec), [1, 2, 4]);
    }

    public function inAndBetween(): void
    {
        Assert::same($this->ids(SpecificationBuilder::create()->whereIn(column: 'price', values: [10, 40])->build()), [1, 4]);
        Assert::same($this->ids(SpecificationBuilder::create()->whereBetween(column: 'price', from: 20, to: 40)->build()), [2, 3, 4]);
    }

    public function notNegatesCondition(): void
    {
        $spec = SpecificationBuilder::create()->notWhere(
            static fn(SpecificationBuilder $b): SpecificationBuilder => $b->whereEqual(column: 'status', value: 'active'),
        )->build();

        Assert::same($this->ids($spec), [3, 5]);
    }

    public function orBranchesIsolateParameters(): void
    {
        $spec = CompositeSpecification::create()->withSpecification(
            specification: OrSpecification::create(
                SpecificationBuilder::create()->whereEqual(column: 'status', value: 'inactive')->build(),
                SpecificationBuilder::create()->whereEqual(column: 'price', value: 20)->build(),
            ),
        );

        Assert::same($this->ids($spec), [2, 3, 5]);
    }

    public function orWhereBuilderKeepsBothSides(): void
    {
        $spec = SpecificationBuilder::create()
            ->whereEqual(column: 'status', value: 'active')
            ->orWhere(static fn(SpecificationBuilder $b): SpecificationBuilder => $b->whereGreaterThanOrEqual(column: 'price', value: 50))
            ->build();

        Assert::same($this->ids($spec), [1, 2, 4, 5]);
    }

    public function andCombinedWithOrManyParameters(): void
    {
        $spec = SpecificationBuilder::create()
            ->whereGreaterThanOrEqual(column: 'price', value: 20)
            ->build()
            ->withSpecification(
                specification: OrSpecification::create(
                    SpecificationBuilder::create()->whereEqual(column: 'status', value: 'inactive')->build(),
                    SpecificationBuilder::create()->whereEqual(column: 'price', value: 20)->build(),
                ),
            );

        Assert::same($this->ids($spec), [2, 3, 5]);
    }

    public function orderByAndLimit(): void
    {
        $spec = CompositeSpecification::create()
            ->withOrderBy(columns: ['created_at' => 'DESC'])
            ->withLimit(limit: 2);

        $query = (new Query($this->db))->select('id')->from('items');
        QueryApplier::apply(specification: $spec, query: $query);

        $ids = array_map($this->rowId(...), $query->all());

        Assert::same($ids, [5, 4]);
    }

    public function orderByLimitOffset(): void
    {
        $spec = CompositeSpecification::create()
            ->withOrderBy(columns: ['price' => 'ASC'])
            ->withLimit(limit: 2)
            ->withOffset(offset: 2);

        $query = (new Query($this->db))->select('id')->from('items');
        QueryApplier::apply(specification: $spec, query: $query);

        $ids = array_map($this->rowId(...), $query->all());

        Assert::same($ids, [3, 4]);
    }

    public function rawCondition(): void
    {
        $spec = CompositeSpecification::create()
            ->withRaw(condition: 'price > :min', params: ['min' => 30]);

        Assert::same($this->ids($spec), [4, 5]);
    }

    public function notBetween(): void
    {
        $spec = SpecificationBuilder::create()
            ->whereNotBetween(column: 'price', from: 20, to: 40)
            ->build();

        Assert::same($this->ids($spec), [1, 5]);
    }

    public function notIn(): void
    {
        $spec = SpecificationBuilder::create()
            ->whereNotIn(column: 'status', values: ['active'])
            ->build();

        Assert::same($this->ids($spec), [3, 5]);
    }

    public function whereNull(): void
    {
        $this->db->createCommand("INSERT INTO items (id, name, status, price, created_at) VALUES (6, 'foxtrot', NULL, 60, '2024-06-01')")->execute();

        $spec = SpecificationBuilder::create()->whereNull(column: 'status')->build();

        Assert::same($this->ids($spec), [6]);
    }

    public function whereNotNull(): void
    {
        $this->db->createCommand("INSERT INTO items (id, name, status, price, created_at) VALUES (6, 'foxtrot', NULL, 60, '2024-06-01')")->execute();

        $spec = SpecificationBuilder::create()->whereNotNull(column: 'status')->build();

        Assert::same($this->ids($spec), [1, 2, 3, 4, 5]);
    }

    public function doubleNotFlattensToAnd(): void
    {
        $spec = CompositeSpecification::create()
            ->withSpecification(
                specification: new NotSpecification(
                    specification: CompositeSpecification::create()
                        ->withSpecification(
                            specification: new NotSpecification(
                                specification: CompositeSpecification::create()
                                    ->withComparison(column: 'status', value: 'active'),
                            ),
                        ),
                ),
            );

        Assert::same($this->ids($spec), [1, 2, 4]);
    }

    public function orConditionFromArray(): void
    {
        $spec = CompositeSpecification::create()
            ->withOrCondition(conditions: ['status' => 'active', 'price' => 50]);

        Assert::same($this->ids($spec), [1, 2, 4, 5]);
    }

    public function builderFullPagination(): void
    {
        $spec = SpecificationBuilder::create()
            ->whereEqual(column: 'status', value: 'active')
            ->orderBy(columns: ['price' => 'ASC'])
            ->limit(limit: 2)
            ->offset(offset: 1)
            ->build();

        $query = (new Query($this->db))->select('id')->from('items');
        QueryApplier::apply(specification: $spec, query: $query);

        $ids = array_map($this->rowId(...), $query->all());

        Assert::same($ids, [2, 4]);
    }

    public function orConditionWithStringListBecomesIn(): void
    {
        $spec = CompositeSpecification::create()
            ->withOrCondition(conditions: ['name' => ['alpha', 'bravo']]);

        Assert::same($this->ids($spec), [1, 2]);
    }
}
