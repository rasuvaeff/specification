<?php

declare(strict_types=1);

namespace Rasuvaeff\Specification\Tests\Integration;

use DateTimeImmutable;
use DateTimeZone;
use Rasuvaeff\Specification\ComparisonSpecification;
use Rasuvaeff\Specification\CompositeSpecification;
use Rasuvaeff\Specification\LikeMatch;
use Rasuvaeff\Specification\NotSpecification;
use Rasuvaeff\Specification\OrConditionSpecification;
use Rasuvaeff\Specification\OrSpecification;
use Rasuvaeff\Specification\QueryApplier;
use Rasuvaeff\Specification\SpecificationBuilder;
use Testo\Assert;
use Testo\Codecov\CoversNothing;
use Testo\Data\DataProvider;
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

    public function directNotSpecificationPreservesPagination(): void
    {
        $spec = CompositeSpecification::create()->withNot(
            specification: CompositeSpecification::create()
                ->withComparison(column: 'status', value: 'active')
                ->withOrderBy(columns: ['price' => 'DESC'])
                ->withLimit(limit: 1)
                ->withOffset(offset: 1),
        );

        Assert::same($this->ids($spec), [3]);
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

    public function orWherePreservesPagination(): void
    {
        $spec = SpecificationBuilder::create()
            ->whereEqual(column: 'status', value: 'active')
            ->orderBy(columns: ['price' => 'DESC'])
            ->limit(limit: 1)
            ->offset(offset: 2)
            ->orWhere(static fn(SpecificationBuilder $b): SpecificationBuilder => $b->whereEqual(column: 'status', value: 'inactive'))
            ->build();

        $query = (new Query($this->db))->select('id')->from('items');
        QueryApplier::apply(specification: $spec, query: $query);

        $ids = array_map($this->rowId(...), $query->all());
        Assert::same($ids, [3]);
    }

    public function orWhereCallbackPreservesItsPagination(): void
    {
        $spec = SpecificationBuilder::create()
            ->whereEqual(column: 'status', value: 'active')
            ->orWhere(static fn(SpecificationBuilder $b): SpecificationBuilder => $b
                ->whereEqual(column: 'status', value: 'inactive')
                ->orderBy(columns: ['price' => 'DESC'])
                ->limit(limit: 1)
                ->offset(offset: 2))
            ->build();

        $query = (new Query($this->db))->select('id')->from('items');
        QueryApplier::apply(specification: $spec, query: $query);

        $ids = array_map($this->rowId(...), $query->all());
        Assert::same($ids, [3]);
    }

    public function nestedOrWhereKeepsNestedBranch(): void
    {
        $this->db->createCommand("INSERT INTO items (id, name, status, price, created_at) VALUES (6, 'foxtrot', 'pending', 60, '2024-06-01')")->execute();

        $spec = SpecificationBuilder::create()->orWhere(
            static fn(SpecificationBuilder $b): SpecificationBuilder => $b
                ->whereEqual(column: 'status', value: 'active')
                ->orWhere(static fn(SpecificationBuilder $nested): SpecificationBuilder => $nested->whereEqual(column: 'status', value: 'pending')),
        )->build();

        Assert::same($this->ids($spec), [1, 2, 4, 6]);
    }

    public function nestedNotWhereKeepsNestedBranch(): void
    {
        $this->db->createCommand("INSERT INTO items (id, name, status, price, created_at) VALUES (6, 'foxtrot', 'pending', 60, '2024-06-01')")->execute();

        $spec = SpecificationBuilder::create()->notWhere(
            static fn(SpecificationBuilder $b): SpecificationBuilder => $b
                ->whereEqual(column: 'status', value: 'active')
                ->orWhere(static fn(SpecificationBuilder $nested): SpecificationBuilder => $nested->whereEqual(column: 'status', value: 'pending')),
        )->build();

        Assert::same($this->ids($spec), [3, 5]);
    }

    public function dateTimeKeepsMicrosecondsAndTimezone(): void
    {
        $this->db->createCommand("INSERT INTO items (id, name, status, price, created_at) VALUES (6, 'foxtrot', 'active', 60, '2024-06-01 03:04:05.123456+03:00')")->execute();

        $dateTime = new DateTimeImmutable('2024-06-01 03:04:05.123456', new DateTimeZone('+03:00'));
        $spec = SpecificationBuilder::create()->whereEqual(column: 'created_at', value: $dateTime)->build();

        Assert::same($this->ids($spec), [6]);
    }

    public function orConditionDateTimeKeepsMicrosecondsAndTimezone(): void
    {
        $this->db->createCommand("INSERT INTO items (id, name, status, price, created_at) VALUES (6, 'foxtrot', 'active', 60, '2024-06-01 03:04:05.123456+03:00')")->execute();

        $dateTime = new DateTimeImmutable('2024-06-01 03:04:05.123456', new DateTimeZone('+03:00'));
        $spec = new CompositeSpecification(specifications: [
            new OrConditionSpecification(conditions: [
                ['=', 'created_at', $dateTime],
            ]),
        ]);

        Assert::same($this->ids($spec), [6]);
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

    /**
     * The structural tests see the array handed to `andWhere()` and nothing
     * of what yiisoft/db does with it — which is to escape the value and wrap
     * it in `%…%` unless told otherwise. A pre-wildcarded value searched for
     * a literal `%`, and `ilike` rendered as a verbatim `ILIKE` that SQLite
     * does not parse (#27).
     *
     * @param list<int> $expected
     */
    #[DataProvider('likeProvider')]
    public function likeAgainstTheRealBuilder(ComparisonSpecification $spec, array $expected): void
    {
        $this->db->createCommand()
            ->insertBatch('items', [
                [6, 'Alpha%Beta', 'active', 60, '2024-06-01'],
                [7, 'Alphabet', 'active', 70, '2024-07-01'],
                [8, 'a_b', 'active', 80, '2024-08-01'],
            ], ['id', 'name', 'status', 'price', 'created_at'])
            ->execute();

        Assert::same($this->ids(CompositeSpecification::create()->withSpecification($spec)), $expected);
    }

    public static function likeProvider(): iterable
    {
        // SQLite's LIKE is case-insensitive for ASCII, hence `alpha` beside `Alpha…`.
        yield 'startsWith adds the wildcard' => [ComparisonSpecification::startsWith(column: 'name', prefix: 'Alpha'), [1, 6, 7]];
        yield 'endsWith adds the wildcard' => [ComparisonSpecification::endsWith(column: 'name', suffix: 'bet'), [7]];
        yield 'contains adds both wildcards' => [ComparisonSpecification::contains(column: 'name', substring: 'lph'), [1, 6, 7]];
        yield 'contains escapes a literal percent' => [ComparisonSpecification::contains(column: 'name', substring: '%'), [6]];
        yield 'contains escapes a literal underscore' => [ComparisonSpecification::contains(column: 'name', substring: '_'), [8]];
        yield 'like sends the pattern verbatim' => [ComparisonSpecification::like(column: 'name', pattern: 'a_b%'), [8]];
        yield 'not like sends the pattern verbatim' => [ComparisonSpecification::notLike(column: 'name', pattern: '%a%'), [5]];
        yield 'ilike is accepted by SQLite' => [ComparisonSpecification::ilike(column: 'name', pattern: '%ALPHA%'), [1, 6, 7]];
        yield 'not ilike is accepted by SQLite' => [ComparisonSpecification::notIlike(column: 'name', pattern: '%A%'), [5]];
        yield 'a match mode combines with ilike' => [new ComparisonSpecification(column: 'name', value: 'LPH', operator: 'ilike', likeMatch: LikeMatch::Contains), [1, 6, 7]];
    }

    /**
     * NOT and OR route the condition through a sub-query and the placeholder
     * remap, so the named operands must survive that path too.
     */
    public function likeInsideNotAndOr(): void
    {
        $this->db->createCommand()
            ->insertBatch('items', [
                [6, 'Alpha%Beta', 'active', 60, '2024-06-01'],
                [7, 'Alphabet', 'active', 70, '2024-07-01'],
            ], ['id', 'name', 'status', 'price', 'created_at'])
            ->execute();

        $not = CompositeSpecification::create()->withSpecification(
            specification: new NotSpecification(specification: ComparisonSpecification::contains(column: 'name', substring: 'lph')),
        );
        Assert::same($this->ids($not), [2, 3, 4, 5]);

        $or = CompositeSpecification::create()->withSpecification(
            specification: OrSpecification::create(
                ComparisonSpecification::contains(column: 'name', substring: '%'),
                ComparisonSpecification::endsWith(column: 'name', suffix: 'bet'),
                ComparisonSpecification::like(column: 'name', pattern: 'e%'),
            ),
        );
        Assert::same($this->ids($or), [5, 6, 7]);
    }

    /**
     * SQLite reads `IS 'pending'` as null-safe equality, so a verbatim `is`
     * passes here while MySQL and PostgreSQL reject it (#34). The SQL is
     * asserted, not only the rows: the rows would be the same either way.
     */
    public function orConditionIsRendersAsEquality(): void
    {
        $spec = CompositeSpecification::create()
            ->withOrCondition(conditions: ['status' => ['is', 'pending'], 'name' => ['is not', null]]);

        $query = (new Query($this->db))->from('items');
        QueryApplier::apply(specification: $spec, query: $query);
        $sql = $query->createCommand()->getRawSql();

        Assert::string($sql)->contains('"status" = \'pending\'')->contains('"name" IS NOT NULL');
        Assert::string($sql)->notContains(' IS \'');
        Assert::same($this->ids($spec), [1, 2, 3, 4, 5]);
    }

    public function orConditionLikeSendsThePatternVerbatim(): void
    {
        $this->db->createCommand()->insert('items', ['id' => 6, 'name' => 'Alpha%Beta', 'status' => 'active', 'price' => 60, 'created_at' => '2024-06-01'])->execute();

        $spec = CompositeSpecification::create()
            ->withOrCondition(conditions: ['name' => ['like', '%\\%%'], 'price' => 50]);

        Assert::same($this->ids($spec), [5, 6]);
    }
}
