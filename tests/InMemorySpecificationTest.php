<?php

declare(strict_types=1);

namespace Rasuvaeff\Specification\Tests;

use DateTimeImmutable;
use Rasuvaeff\Specification\ComparisonSpecification;
use Rasuvaeff\Specification\CompositeSpecification;
use Rasuvaeff\Specification\InMemorySpecification;
use Rasuvaeff\Specification\LimitSpecification;
use Rasuvaeff\Specification\NotSpecification;
use Rasuvaeff\Specification\OffsetSpecification;
use Rasuvaeff\Specification\OrConditionSpecification;
use Rasuvaeff\Specification\OrderBySpecification;
use Rasuvaeff\Specification\OrSpecification;
use Rasuvaeff\Specification\RawSpecification;
use Rasuvaeff\Specification\Specification;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;

/**
 * The in-memory interpreter: the same specification that QueryBuildingVisitor
 * renders into SQL answers a plain bool for one row. The SQL-coercion semantic
 * the package pins (#40): SQL compares after type coercion, `in_array(strict:
 * true)` does not — `'1'` against `1` must agree in memory the way SQL would.
 */
#[Test]
#[Covers(InMemorySpecification::class)]
final class InMemorySpecificationTest
{
    /** @param array<string, mixed> $row */
    private function satisfies(Specification $specification, array $row): bool
    {
        return InMemorySpecification::satisfies(specification: $specification, row: $row);
    }

    public function evaluatesScalarComparisons(): void
    {
        $row = ['age' => 25, 'name' => 'bravo'];

        Assert::true($this->satisfies(ComparisonSpecification::equal(column: 'age', value: 25), $row));
        Assert::false($this->satisfies(ComparisonSpecification::equal(column: 'age', value: 26), $row));
        Assert::true($this->satisfies(ComparisonSpecification::notEqual(column: 'age', value: 26), $row));
        Assert::true($this->satisfies(ComparisonSpecification::greaterThan(column: 'age', value: 24), $row));
        Assert::false($this->satisfies(ComparisonSpecification::greaterThan(column: 'age', value: 25), $row));
        Assert::true($this->satisfies(ComparisonSpecification::greaterThanOrEqual(column: 'age', value: 25), $row));
        Assert::true($this->satisfies(ComparisonSpecification::lessThan(column: 'age', value: 26), $row));
        Assert::false($this->satisfies(ComparisonSpecification::lessThan(column: 'age', value: 25), $row));
        Assert::true($this->satisfies(ComparisonSpecification::lessThanOrEqual(column: 'age', value: 25), $row));

        // Non-numeric strings compare byte-wise, as SQL's BINARY collation does.
        Assert::true($this->satisfies(ComparisonSpecification::equal(column: 'name', value: 'bravo'), $row));
        Assert::true($this->satisfies(ComparisonSpecification::greaterThan(column: 'name', value: 'alpha'), $row));
        Assert::false($this->satisfies(ComparisonSpecification::greaterThan(column: 'name', value: 'bravo'), $row));

        // `<>` is the SQL spelling of `!=`.
        Assert::true($this->satisfies(new ComparisonSpecification(column: 'age', value: 26, operator: '<>'), $row));
    }

    /**
     * The pin of #40: SQL compares after type coercion — a `'1'` column
     * against `1` agrees in SQL and must agree in memory. Booleans coerce to
     * 1/0 exactly as a driver hands them to SQL.
     */
    public function comparesAfterSqlTypeCoercion(): void
    {
        $row = ['code' => '1', 'count' => 1, 'flag' => true];

        Assert::true($this->satisfies(ComparisonSpecification::equal(column: 'code', value: 1), $row));
        Assert::true($this->satisfies(ComparisonSpecification::equal(column: 'count', value: '1'), $row));
        Assert::true($this->satisfies(ComparisonSpecification::notEqual(column: 'code', value: 2), $row));
        Assert::true($this->satisfies(ComparisonSpecification::greaterThan(column: 'code', value: 0), $row));
        Assert::true($this->satisfies(ComparisonSpecification::equal(column: 'flag', value: 1), $row));
        Assert::true($this->satisfies(ComparisonSpecification::equal(column: 'flag', value: '1'), $row));
        Assert::true($this->satisfies(ComparisonSpecification::equal(column: 'count', value: 1.0), $row));

        // A scientific-notation string narrows to the float it spells.
        Assert::true($this->satisfies(ComparisonSpecification::equal(column: 'code', value: '1e3'), ['code' => 1000]));
        Assert::false($this->satisfies(ComparisonSpecification::equal(column: 'code', value: '1e2'), ['code' => 1000]));

        // A numeric-string ordering agrees with SQL's numeric conversion:
        // '20' > '9' numerically, though not byte-wise.
        $numericRow = ['code' => '20'];
        Assert::true($this->satisfies(ComparisonSpecification::greaterThan(column: 'code', value: '9'), $numericRow));
        Assert::false($this->satisfies(ComparisonSpecification::lessThan(column: 'code', value: '9'), $numericRow));
    }

    public function nullValueIsAnIsNullCheck(): void
    {
        $withNull = ['deleted_at' => null];
        $withDate = ['deleted_at' => '2024-01-01'];

        // `= null` renders as IS NULL (#34), `!=`/`is not` as IS NOT NULL.
        Assert::true($this->satisfies(ComparisonSpecification::equal(column: 'deleted_at', value: null), $withNull));
        Assert::false($this->satisfies(ComparisonSpecification::equal(column: 'deleted_at', value: null), $withDate));
        Assert::false($this->satisfies(ComparisonSpecification::notEqual(column: 'deleted_at', value: null), $withNull));
        Assert::true($this->satisfies(ComparisonSpecification::notEqual(column: 'deleted_at', value: null), $withDate));
        Assert::true($this->satisfies(ComparisonSpecification::isNull(column: 'deleted_at'), $withNull));
        Assert::false($this->satisfies(ComparisonSpecification::isNull(column: 'deleted_at'), $withDate));
        Assert::true($this->satisfies(ComparisonSpecification::isNotNull(column: 'deleted_at'), $withDate));

        // `is` / `is not` with a non-NULL value are plain equality.
        Assert::true($this->satisfies(new ComparisonSpecification(column: 'deleted_at', value: '2024-01-01', operator: 'is'), $withDate));
        Assert::false($this->satisfies(new ComparisonSpecification(column: 'deleted_at', value: '2024-01-01', operator: 'is not'), $withDate));
    }

    /**
     * SQL: NULL never satisfies a scalar comparison or a BETWEEN, whatever
     * the operator — the row is simply not selected.
     */
    public function nullRowValueSatisfiesNothing(): void
    {
        $row = ['price' => null];

        Assert::false($this->satisfies(ComparisonSpecification::equal(column: 'price', value: 10), $row));
        Assert::false($this->satisfies(ComparisonSpecification::notEqual(column: 'price', value: 10), $row));
        Assert::false($this->satisfies(ComparisonSpecification::greaterThan(column: 'price', value: 0), $row));
        Assert::false($this->satisfies(ComparisonSpecification::between(column: 'price', from: 0, to: 100), $row));

        // IN/NOT IN carry yiisoft/db's own NULL handling — see below.
        Assert::false($this->satisfies(ComparisonSpecification::in(column: 'price', values: [10, 20]), $row));
        Assert::false($this->satisfies(ComparisonSpecification::notIn(column: 'price', values: [30]), $row));
    }

    /**
     * A NULL entry of an IN/NOT IN list is what yiisoft/db renders it as:
     * `IS NULL` beside the equality arms for IN, `IS NOT NULL` for NOT IN.
     * An empty NOT IN list adds no condition at all; an empty IN list is
     * `0=1`. Verified against in-memory SQLite.
     */
    public function nullEntriesFollowTheConditionBuildersReading(): void
    {
        $row = ['status' => 'active'];
        $nullRow = ['status' => null];

        // IN (NULL) renders as `status IS NULL`.
        Assert::false($this->satisfies(ComparisonSpecification::in(column: 'status', values: [null]), $row));
        Assert::true($this->satisfies(ComparisonSpecification::in(column: 'status', values: [null]), $nullRow));

        // IN ('new', NULL) renders as `status = 'new' OR status IS NULL`.
        Assert::true($this->satisfies(ComparisonSpecification::in(column: 'status', values: ['new', null]), $nullRow));
        Assert::false($this->satisfies(ComparisonSpecification::in(column: 'status', values: ['new', null]), $row));

        // The NULL entry may also come first — a match after it still counts.
        Assert::true($this->satisfies(ComparisonSpecification::in(column: 'status', values: [null, 'active']), $row));

        // NOT IN ('new', NULL) renders as `status <> 'new' AND status IS NOT NULL`.
        Assert::true($this->satisfies(ComparisonSpecification::notIn(column: 'status', values: ['new', null]), $row));
        Assert::false($this->satisfies(ComparisonSpecification::notIn(column: 'status', values: ['active', null]), $row));
        Assert::false($this->satisfies(ComparisonSpecification::notIn(column: 'status', values: ['new', null]), $nullRow));

        // An empty NOT IN adds no condition — NULL rows included.
        Assert::true($this->satisfies(ComparisonSpecification::notIn(column: 'status', values: []), $nullRow));
    }

    /**
     * The coercion pin inside a list: '1' against [1].
     */
    public function evaluatesInAndNotIn(): void
    {
        $row = ['status' => 'active'];

        Assert::true($this->satisfies(ComparisonSpecification::in(column: 'status', values: ['active', 'new']), $row));
        Assert::false($this->satisfies(ComparisonSpecification::in(column: 'status', values: ['new', 'banned']), $row));
        Assert::true($this->satisfies(ComparisonSpecification::notIn(column: 'status', values: ['new', 'banned']), $row));
        Assert::false($this->satisfies(ComparisonSpecification::notIn(column: 'status', values: ['active']), $row));

        Assert::true($this->satisfies(ComparisonSpecification::in(column: 'code', values: [1, 2]), ['code' => '1']));

        // An empty list selects nothing for IN, everything for NOT IN.
        Assert::false($this->satisfies(ComparisonSpecification::in(column: 'status', values: []), $row));
        Assert::true($this->satisfies(ComparisonSpecification::notIn(column: 'status', values: []), $row));
    }

    public function evaluatesBetweenAndNotBetween(): void
    {
        $row = ['price' => 25];

        Assert::true($this->satisfies(ComparisonSpecification::between(column: 'price', from: 20, to: 30), $row));
        Assert::false($this->satisfies(ComparisonSpecification::between(column: 'price', from: 26, to: 30), $row));
        Assert::true($this->satisfies(ComparisonSpecification::notBetween(column: 'price', from: 26, to: 30), $row));
        Assert::false($this->satisfies(ComparisonSpecification::notBetween(column: 'price', from: 20, to: 30), $row));

        // A NULL bound makes the predicate NULL, and NOT of it stays NULL.
        Assert::false($this->satisfies(new ComparisonSpecification(column: 'price', value: [null, 30], operator: 'between'), $row));
        Assert::false($this->satisfies(new ComparisonSpecification(column: 'price', value: [null, 30], operator: 'not between'), $row));
    }

    public function composesAndOrNot(): void
    {
        $row = ['status' => 'active', 'price' => 25];

        $and = new CompositeSpecification(specifications: [
            ComparisonSpecification::equal(column: 'status', value: 'active'),
            ComparisonSpecification::greaterThan(column: 'price', value: 20),
        ]);
        Assert::true($this->satisfies($and, $row));

        $andFailing = new CompositeSpecification(specifications: [
            ComparisonSpecification::equal(column: 'status', value: 'active'),
            ComparisonSpecification::greaterThan(column: 'price', value: 30),
        ]);
        Assert::false($this->satisfies($andFailing, $row));

        // An empty composite adds no condition: vacuously true, as the SQL
        // visitor adds none.
        Assert::true($this->satisfies(CompositeSpecification::create(), $row));

        $or = OrSpecification::create(
            ComparisonSpecification::equal(column: 'status', value: 'banned'),
            ComparisonSpecification::greaterThan(column: 'price', value: 20),
        );
        Assert::true($this->satisfies($or, $row));

        $orFailing = OrSpecification::create(
            ComparisonSpecification::equal(column: 'status', value: 'banned'),
            ComparisonSpecification::greaterThan(column: 'price', value: 30),
        );
        Assert::false($this->satisfies($orFailing, $row));

        // An empty OR leaves the row set unconstrained.
        Assert::true($this->satisfies(OrSpecification::create(), $row));

        Assert::false($this->satisfies(new NotSpecification(ComparisonSpecification::equal(column: 'status', value: 'active')), $row));
        Assert::true($this->satisfies(new NotSpecification(ComparisonSpecification::equal(column: 'status', value: 'banned')), $row));

        // NOT NOT collapses to the inner specification, as the SQL visitor
        // skips the subquery for a double negation.
        $doubleNegation = new NotSpecification(new NotSpecification(ComparisonSpecification::equal(column: 'status', value: 'active')));
        Assert::true($this->satisfies($doubleNegation, $row));
    }

    /**
     * UNKNOWN projects to `false` at every boundary — what a WHERE does —
     * and stays unknown under NOT and under the folds, exactly as SQL's
     * three-valued logic has it.
     */
    public function unknownStaysUnknownThroughComposition(): void
    {
        $nullRow = ['price' => null, 'status' => 'active'];

        // A scalar comparison over a NULL row value is UNKNOWN, not false.
        Assert::false($this->satisfies(ComparisonSpecification::greaterThan(column: 'price', value: 0), $nullRow));

        // UNKNOWN under AND: TRUE AND UNKNOWN is UNKNOWN.
        Assert::false($this->satisfies(new CompositeSpecification(specifications: [
            ComparisonSpecification::equal(column: 'status', value: 'active'),
            ComparisonSpecification::greaterThan(column: 'price', value: 0),
        ]), $nullRow));

        // UNKNOWN under OR: FALSE OR UNKNOWN is UNKNOWN — and TRUE dominates.
        Assert::false($this->satisfies(OrSpecification::create(
            ComparisonSpecification::equal(column: 'status', value: 'banned'),
            ComparisonSpecification::greaterThan(column: 'price', value: 0),
        ), $nullRow));
        Assert::true($this->satisfies(OrSpecification::create(
            ComparisonSpecification::equal(column: 'status', value: 'active'),
            ComparisonSpecification::greaterThan(column: 'price', value: 0),
        ), $nullRow));

        // UNKNOWN under NOT: NOT UNKNOWN is UNKNOWN — the row stays out.
        Assert::false($this->satisfies(new NotSpecification(ComparisonSpecification::greaterThan(column: 'price', value: 0)), $nullRow));
        // …and the double negation collapses to the inner UNKNOWN.
        Assert::false($this->satisfies(new NotSpecification(new NotSpecification(ComparisonSpecification::greaterThan(column: 'price', value: 0))), $nullRow));

        // NOT over a fold: NOT (FALSE OR FALSE) is TRUE — the mutant trap of
        // an OR that marks its FALSE children unknown.
        Assert::true($this->satisfies(new NotSpecification(OrSpecification::create(
            ComparisonSpecification::equal(column: 'status', value: 'banned'),
            ComparisonSpecification::equal(column: 'status', value: 'new'),
        )), $nullRow));

        // NOT over a group that carries real conditions is a real negation.
        Assert::false($this->satisfies(new NotSpecification(new CompositeSpecification(specifications: [
            ComparisonSpecification::equal(column: 'status', value: 'active'),
        ])), $nullRow));

        // A BETWEEN with a NULL bound is UNKNOWN for every row, either
        // polarity, and stays so under NOT.
        Assert::false($this->satisfies(new ComparisonSpecification(column: 'price', value: [null, 30], operator: 'between'), $nullRow));
        Assert::false($this->satisfies(new NotSpecification(new ComparisonSpecification(column: 'price', value: [null, 30], operator: 'between')), $nullRow));

        // An IN over a NULL-less list and a NULL row is UNKNOWN — under NOT
        // too, as its `= x` rendering is.
        Assert::false($this->satisfies(new NotSpecification(ComparisonSpecification::in(column: 'status', values: ['banned'])), ['status' => null]));
        Assert::false($this->satisfies(new NotSpecification(ComparisonSpecification::notIn(column: 'status', values: ['banned'])), ['status' => null]));
    }

    /**
     * Ordering, LIMIT and OFFSET do not filter rows — neither in SQL's WHERE
     * nor here, nested or alone.
     */
    public function ignoresOrderLimitOffset(): void
    {
        $row = ['price' => 25];

        Assert::true($this->satisfies(new OrderBySpecification(['price' => 'DESC']), $row));
        Assert::true($this->satisfies(new LimitSpecification(10), $row));
        Assert::true($this->satisfies(new OffsetSpecification(5), $row));
        Assert::true($this->satisfies(new CompositeSpecification(specifications: [
            new LimitSpecification(10),
            ComparisonSpecification::equal(column: 'price', value: 25),
        ]), $row));

        // Nested beside a failing condition they still add nothing: the
        // comparison alone decides.
        Assert::false($this->satisfies(new CompositeSpecification(specifications: [
            new OrderBySpecification(['price' => 'DESC']),
            new OffsetSpecification(5),
            ComparisonSpecification::equal(column: 'price', value: 26),
        ]), $row));
        Assert::true($this->satisfies(OrSpecification::create(
            new LimitSpecification(10),
            ComparisonSpecification::equal(column: 'price', value: 25),
        ), $row));
    }

    /**
     * Everything the interpreter cannot name in memory is refused with a
     * LogicException saying what — never guessed.
     */
    public function refusesWhatItCannotEvaluate(): void
    {
        $row = ['name' => 'alpha', 'tags' => ['a']];

        $refusals = [
            'like' => [ComparisonSpecification::like(column: 'name', pattern: 'al%'), 'like'],
            'not like' => [ComparisonSpecification::notLike(column: 'name', pattern: 'al%'), 'like'],
            'ilike' => [ComparisonSpecification::ilike(column: 'name', pattern: 'al%'), 'like'],
            'raw SQL' => [new RawSpecification('1 = 1'), 'Raw SQL'],
            'raw OR conditions' => [OrConditionSpecification::fromArray([['status' => 'active'], ['status' => 'new']]), 'Raw OR'],
            'date value' => [ComparisonSpecification::greaterThan(column: 'created_at', value: new DateTimeImmutable('2024-01-01')), 'Date value'],
            'missing column' => [ComparisonSpecification::equal(column: 'nope', value: 1), 'not present in the row'],
            'ambiguous string against number' => [ComparisonSpecification::greaterThan(column: 'name', value: 0), 'Ambiguous comparison'],
            'non-scalar row value' => [ComparisonSpecification::equal(column: 'tags', value: 'a'), 'cannot be evaluated in memory'],
            'NOT of nothing' => [new NotSpecification(CompositeSpecification::create()), 'cannot be empty'],
            'NOT of an empty OR' => [new NotSpecification(OrSpecification::create()), 'cannot be empty'],
            'raw SQL nested in a group' => [new CompositeSpecification(specifications: [new RawSpecification('1 = 1')]), 'Raw SQL'],
            'raw OR nested in a group' => [new CompositeSpecification(specifications: [OrConditionSpecification::fromArray([['a' => 1]])]), 'Raw OR'],
            'raw SQL under NOT' => [new NotSpecification(new RawSpecification('1 = 1')), 'Raw SQL'],
        ];

        foreach ($refusals as $label => [$specification, $needle]) {
            $thrown = null;

            try {
                $this->satisfies($specification, $row);
            } catch (\LogicException $exception) {
                $thrown = $exception;
            }

            Assert::notNull($thrown, message: "Expected \"$label\" to be refused");
            Assert::string($thrown->getMessage())->contains($needle);
        }
    }
}
