<?php

declare(strict_types=1);

namespace Rasuvaeff\Specification\Tests;

use DateTimeImmutable;
use InvalidArgumentException;
use Rasuvaeff\PropertyTesting\ArbitraryInterface;
use Rasuvaeff\PropertyTesting\Gen;
use Rasuvaeff\PropertyTesting\Property;
use Rasuvaeff\Specification\ComparisonSpecification;
use Rasuvaeff\Specification\CompositeSpecification;
use Rasuvaeff\Specification\LikeMatch;
use Rasuvaeff\Specification\LimitSpecification;
use Rasuvaeff\Specification\NotSpecification;
use Rasuvaeff\Specification\OffsetSpecification;
use Rasuvaeff\Specification\OrConditionSpecification;
use Rasuvaeff\Specification\OrderBySpecification;
use Rasuvaeff\Specification\OrSpecification;
use Rasuvaeff\Specification\QueryBuildingVisitor;
use Rasuvaeff\Specification\RawSpecification;
use Rasuvaeff\Specification\SpecificationBuilder;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
use Testo\Expect;
use Testo\Test;
use Yiisoft\Db\Query\Query;
use Yiisoft\Db\QueryBuilder\Condition\LikeMode;

use const SORT_ASC;
use const SORT_DESC;

#[Test]
#[Covers(QueryBuildingVisitor::class)]
final class QueryBuildingVisitorTest
{
    private function makeQuery(): Query
    {
        return new Query(db: new FakeConnection());
    }

    public function visitComparisonSimpleOperator(): void
    {
        $query = $this->makeQuery();
        $visitor = new QueryBuildingVisitor(query: $query);
        $spec = new ComparisonSpecification(column: 'age', value: 25, operator: '>');

        $visitor->visitComparison(specification: $spec);

        Assert::same($query->getWhere(), ['>', 'age', 25]);
    }

    public function visitComparisonBetweenOperator(): void
    {
        $query = $this->makeQuery();
        $visitor = new QueryBuildingVisitor(query: $query);
        $spec = new ComparisonSpecification(column: 'age', value: [18, 65], operator: 'between');

        $visitor->visitComparison(specification: $spec);

        Assert::same($query->getWhere(), ['between', 'age', 18, 65]);
    }

    public function visitComparisonBetweenInvalidArray(): void
    {
        Expect::exception(InvalidArgumentException::class)->withMessageContaining('Operator "between" requires array with exactly two values');

        $query = $this->makeQuery();
        $visitor = new QueryBuildingVisitor(query: $query);
        $spec = new ComparisonSpecification(column: 'age', value: [18], operator: 'between');
        $visitor->visitComparison(specification: $spec);
    }

    public function visitComparisonInOperator(): void
    {
        $query = $this->makeQuery();
        $visitor = new QueryBuildingVisitor(query: $query);
        $spec = new ComparisonSpecification(column: 'status', value: ['active', 'pending'], operator: 'in');

        $visitor->visitComparison(specification: $spec);

        Assert::same($query->getWhere(), ['in', 'status', ['active', 'pending']]);
    }

    public function visitComparisonIsOperator(): void
    {
        $query = $this->makeQuery();
        $visitor = new QueryBuildingVisitor(query: $query);
        $spec = new ComparisonSpecification(column: 'deleted_at', value: null, operator: 'is');

        $visitor->visitComparison(specification: $spec);

        Assert::same($query->getWhere(), ['=', 'deleted_at', null]);
    }

    public function visitComparisonIsNotOperator(): void
    {
        $query = $this->makeQuery();
        $visitor = new QueryBuildingVisitor(query: $query);
        $spec = new ComparisonSpecification(column: 'deleted_at', value: null, operator: 'is not');

        $visitor->visitComparison(specification: $spec);

        Assert::same($query->getWhere(), ['!=', 'deleted_at', null]);
    }

    public function visitComposite(): void
    {
        $query = $this->makeQuery();
        $visitor = new QueryBuildingVisitor(query: $query);
        $spec1 = new ComparisonSpecification(column: 'status', value: 'active');
        $spec2 = new ComparisonSpecification(column: 'age', value: 18, operator: '>');
        $composite = new CompositeSpecification(specifications: [$spec1, $spec2]);

        $visitor->visitComposite(specification: $composite);

        Assert::same($query->getWhere(), ['and', ['=', 'status', 'active'], ['>', 'age', 18]]);
    }

    public function visitOrCondition(): void
    {
        $query = $this->makeQuery();
        $visitor = new QueryBuildingVisitor(query: $query);
        $spec = new OrConditionSpecification(conditions: [
            ['status' => 'active'],
            ['>', 'age', 18],
        ]);

        $visitor->visitOrCondition(specification: $spec);

        Assert::same($query->getWhere(), ['or', ['status' => 'active'], ['>', 'age', 18]]);
    }

    /**
     * @param array<array-key, mixed> $condition
     * @param array<array-key, mixed> $expected
     */
    #[DataProvider('orConditionLikeProvider')]
    public function visitOrConditionSendsLikePatternsVerbatim(array $condition, array $expected): void
    {
        $query = $this->makeQuery();
        $visitor = new QueryBuildingVisitor(query: $query);
        $spec = new OrConditionSpecification(conditions: [['status' => 'active'], $condition]);

        $visitor->visitOrCondition(specification: $spec);

        Assert::same($query->getWhere(), ['or', ['status' => 'active'], $expected]);
    }

    public static function orConditionLikeProvider(): iterable
    {
        yield 'like' => [
            ['like', 'name', '%john%'],
            ['like', 'name', '%john%', 'mode' => LikeMode::Custom, 'escape' => false],
        ];
        yield 'NOT LIKE in upper case' => [
            ['NOT LIKE', 'name', '%john%'],
            ['not like', 'name', '%john%', 'mode' => LikeMode::Custom, 'escape' => false],
        ];
        yield 'ilike' => [
            ['ilike', 'name', '%john%'],
            ['like', 'name', '%john%', 'caseSensitive' => false, 'mode' => LikeMode::Custom, 'escape' => false],
        ];
        yield 'not ilike' => [
            ['not ilike', 'name', '%john%'],
            ['not like', 'name', '%john%', 'caseSensitive' => false, 'mode' => LikeMode::Custom, 'escape' => false],
        ];
        yield 'a hash condition is untouched' => [
            ['name' => 'john'],
            ['name' => 'john'],
        ];
        yield 'another operator is untouched' => [
            ['>', 'age', 18],
            ['>', 'age', 18],
        ];
        yield 'a like with a non-string pattern is untouched' => [
            ['like', 'name', 5],
            ['like', 'name', 5],
        ];
        yield 'a like with a non-string column is untouched' => [
            ['like', ['name'], 'x'],
            ['like', ['name'], 'x'],
        ];
        yield 'a like with four operands is untouched' => [
            ['like', 'name', 'x', 'y'],
            ['like', 'name', 'x', 'y'],
        ];
    }

    /**
     * @param array<array-key, mixed> $condition
     * @param array<array-key, mixed> $expected
     */
    #[DataProvider('orConditionIsProvider')]
    public function visitOrConditionMapsIsToEqualityLikeVisitComparison(array $condition, array $expected): void
    {
        $query = $this->makeQuery();
        $visitor = new QueryBuildingVisitor(query: $query);
        $spec = new OrConditionSpecification(conditions: [['status' => 'active'], $condition]);

        $visitor->visitOrCondition(specification: $spec);

        Assert::same($query->getWhere(), ['or', ['status' => 'active'], $expected]);
    }

    public static function orConditionIsProvider(): iterable
    {
        yield 'is null' => [['is', 'deleted_at', null], ['=', 'deleted_at', null]];
        yield 'is not null' => [['is not', 'deleted_at', null], ['!=', 'deleted_at', null]];
        yield 'IS in upper case' => [['IS', 'deleted_at', null], ['=', 'deleted_at', null]];
        yield 'is with a string operand' => [['is', 'status', 'pending'], ['=', 'status', 'pending']];
        yield 'is not with a string operand' => [['is not', 'status', 'pending'], ['!=', 'status', 'pending']];
        yield 'is with a bool operand' => [['is', 'flag', true], ['=', 'flag', true]];
        yield 'an is with a non-string column is untouched' => [['is', ['deleted_at'], null], ['is', ['deleted_at'], null]];
        yield 'an is with two operands is untouched' => [['is', 'deleted_at'], ['is', 'deleted_at']];
    }

    public function visitOrConditionEmpty(): void
    {
        $query = $this->makeQuery();
        $visitor = new QueryBuildingVisitor(query: $query);
        $spec = new OrConditionSpecification(conditions: []);

        $visitor->visitOrCondition(specification: $spec);

        Assert::null($query->getWhere());
    }

    public function visitRawStringCondition(): void
    {
        $query = $this->makeQuery();
        $visitor = new QueryBuildingVisitor(query: $query);
        $spec = new RawSpecification(condition: 'age > :age', params: [':age' => 18]);

        $visitor->visitRaw(specification: $spec);

        Assert::same($query->getWhere(), 'age > :age');
        Assert::same($query->getParams(), [':age' => 18]);
    }

    public function visitRawArrayCondition(): void
    {
        $query = $this->makeQuery();
        $visitor = new QueryBuildingVisitor(query: $query);
        $condition = ['or', ['a' => 1], ['b' => 2]];
        $params = ['a' => 1, 'b' => 2];
        $spec = new RawSpecification(condition: $condition, params: $params);

        $visitor->visitRaw(specification: $spec);

        Assert::same($query->getWhere(), $condition);
        Assert::same($query->getParams(), [':a' => 1, ':b' => 2]);
    }

    public function visitRawRenamesPlaceholdersCollidingWithTheQuery(): void
    {
        $query = $this->makeQuery();
        $query->where(condition: 'sort > :sort', params: [':sort' => 20]);

        $visitor = new QueryBuildingVisitor(query: $query);
        $visitor->visitRaw(specification: new RawSpecification(condition: 'sort < :sort', params: [':sort' => 40]));

        Assert::same($query->getWhere(), ['and', 'sort > :sort', 'sort < :sort_0']);
        Assert::same($query->getParams(), [':sort' => 20, ':sort_0' => 40]);
    }

    /**
     * Issue #31: two raw leaves under one AND reused `:sort`, and the second
     * value overwrote the first — the result depended on the order of the leaves.
     */
    public function visitCompositeKeepsBothValuesOfARepeatedRawPlaceholder(): void
    {
        $a = new RawSpecification(condition: 'sort > :sort', params: [':sort' => 20]);
        $b = new RawSpecification(condition: 'sort < :sort', params: [':sort' => 40]);

        $query = $this->makeQuery();
        (CompositeSpecification::create()->withSpecification($a)->withSpecification($b))
            ->accept(new QueryBuildingVisitor(query: $query));
        Assert::same($query->getWhere(), ['and', 'sort > :sort', 'sort < :sort_0']);
        Assert::same($query->getParams(), [':sort' => 20, ':sort_0' => 40]);

        $query = $this->makeQuery();
        (CompositeSpecification::create()->withSpecification($b)->withSpecification($a))
            ->accept(new QueryBuildingVisitor(query: $query));
        Assert::same($query->getWhere(), ['and', 'sort < :sort', 'sort > :sort_0']);
        Assert::same($query->getParams(), [':sort' => 40, ':sort_0' => 20]);
    }

    public function visitRawTreatsColonlessAndColonKeysAsTheSamePlaceholder(): void
    {
        $query = $this->makeQuery();
        $visitor = new QueryBuildingVisitor(query: $query);

        $visitor->visitRaw(specification: new RawSpecification(condition: 'sort > :sort', params: ['sort' => 20]));
        $visitor->visitRaw(specification: new RawSpecification(condition: 'sort < :sort', params: [':sort' => 40]));

        Assert::same($query->getWhere(), ['and', 'sort > :sort', 'sort < :sort_0']);
        Assert::same($query->getParams(), [':sort' => 20, ':sort_0' => 40]);
    }

    public function visitRawLeavesPositionalParamsUntouched(): void
    {
        $query = $this->makeQuery();
        $visitor = new QueryBuildingVisitor(query: $query);

        $visitor->visitRaw(specification: new RawSpecification(condition: 'sort > ? AND sort < ?', params: [1 => 20, 2 => 40]));

        Assert::same($query->getWhere(), 'sort > ? AND sort < ?');
        Assert::same($query->getParams(), [1 => 20, 2 => 40]);
    }

    public function visitRawPicksTheNextFreeSuffixWhenTheRenamedNameIsTakenToo(): void
    {
        $query = $this->makeQuery();
        $query->where(condition: 'sort > :sort AND sort < :sort_0', params: [':sort' => 0, ':sort_0' => 100]);

        $visitor = new QueryBuildingVisitor(query: $query);
        $visitor->visitRaw(specification: new RawSpecification(condition: 'sort <> :sort', params: [':sort' => 50]));

        Assert::same($query->getWhere(), ['and', 'sort > :sort AND sort < :sort_0', 'sort <> :sort_1']);
        Assert::same($query->getParams(), [':sort' => 0, ':sort_0' => 100, ':sort_1' => 50]);
    }

    /**
     * Issue #30: `strtr()` rewrote every occurrence of the renamed `:sort`,
     * including the prefix of `:sort_max`, which then referenced a parameter
     * nobody had bound.
     */
    #[DataProvider('wholeTokenRenameProvider')]
    public function visitNotRenamesWholeTokensOnly(string $condition, string $expected): void
    {
        $query = $this->makeQuery();
        $query->where(condition: 'sort > :sort', params: [':sort' => 0]);

        $visitor = new QueryBuildingVisitor(query: $query);
        $visitor->visitNot(specification: new NotSpecification(
            specification: new RawSpecification(condition: $condition, params: [':sort' => 15, ':sort_max' => 45]),
        ));

        Assert::same($query->getWhere(), ['and', 'sort > :sort', ['not', $expected]]);
        Assert::same($query->getParams(), [':sort' => 0, ':sort_0' => 15, ':sort_max' => 45]);
    }

    public static function wholeTokenRenameProvider(): iterable
    {
        yield 'sibling placeholder with the renamed one as prefix' => [
            'sort BETWEEN :sort AND :sort_max',
            'sort BETWEEN :sort_0 AND :sort_max',
        ];
        yield 'prefixed sibling first' => [
            'sort <= :sort_max AND sort >= :sort',
            'sort <= :sort_max AND sort >= :sort_0',
        ];
        yield 'renamed placeholder at the end of the string' => [
            'sort < :sort_max AND sort > :sort',
            'sort < :sort_max AND sort > :sort_0',
        ];
        yield 'placeholder followed by punctuation' => [
            'sort IN (:sort,:sort_max)',
            'sort IN (:sort_0,:sort_max)',
        ];
        yield 'a ::type cast is not a placeholder' => [
            'sort::sort = :sort AND sort <= :sort_max',
            'sort::sort = :sort_0 AND sort <= :sort_max',
        ];
    }

    /**
     * A leaf already renamed inside the sub-query (`:v` → `:v_0`) collides a
     * second time at the NOT boundary; one pass over the original string keeps
     * the two renames from cascading into each other.
     */
    public function visitNotRenamesTwiceCollidingPlaceholdersInOnePass(): void
    {
        $query = $this->makeQuery();
        $query->where(condition: 'sort > :v', params: [':v' => 0]);

        $visitor = new QueryBuildingVisitor(query: $query);
        $visitor->visitNot(specification: new NotSpecification(
            specification: CompositeSpecification::create()
                ->withSpecification(new RawSpecification(condition: 'sort = :v', params: [':v' => 20]))
                ->withSpecification(new RawSpecification(condition: 'sort = :v', params: [':v' => 30])),
        ));

        Assert::same($query->getWhere(), ['and', 'sort > :v', ['not', ['and', 'sort = :v_0', 'sort = :v_0_0']]]);
        Assert::same($query->getParams(), [':v' => 0, ':v_0' => 20, ':v_0_0' => 30]);
    }

    public function visitComparisonNotBetweenOperator(): void
    {
        $query = $this->makeQuery();
        $visitor = new QueryBuildingVisitor(query: $query);
        $spec = new ComparisonSpecification(column: 'age', value: [18, 65], operator: 'not between');

        $visitor->visitComparison(specification: $spec);

        Assert::same($query->getWhere(), ['not between', 'age', 18, 65]);
    }

    public function visitComparisonNotInOperator(): void
    {
        $query = $this->makeQuery();
        $visitor = new QueryBuildingVisitor(query: $query);
        $spec = new ComparisonSpecification(column: 'status', value: ['banned', 'deleted'], operator: 'not in');

        $visitor->visitComparison(specification: $spec);

        Assert::same($query->getWhere(), ['not in', 'status', ['banned', 'deleted']]);
    }

    public function visitComparisonNotBetweenInvalidArray(): void
    {
        Expect::exception(InvalidArgumentException::class)->withMessageContaining('requires array with exactly two values');

        $query = $this->makeQuery();
        $visitor = new QueryBuildingVisitor(query: $query);
        $spec = new ComparisonSpecification(column: 'age', value: [18], operator: 'not between');
        $visitor->visitComparison(specification: $spec);
    }

    public function visitComparisonNotInInvalidValue(): void
    {
        Expect::exception(InvalidArgumentException::class)->withMessageContaining('requires array value');

        $query = $this->makeQuery();
        $visitor = new QueryBuildingVisitor(query: $query);
        $spec = new ComparisonSpecification(column: 'age', value: 'not_array', operator: 'not in');
        $visitor->visitComparison(specification: $spec);
    }

    public function visitComparisonDefaultOperator(): void
    {
        $query = $this->makeQuery();
        $visitor = new QueryBuildingVisitor(query: $query);
        $spec = new ComparisonSpecification(column: 'name', value: 'John', operator: '!=');

        $visitor->visitComparison(specification: $spec);

        Assert::same($query->getWhere(), ['!=', 'name', 'John']);
    }

    /**
     * yiisoft/db escapes the value and wraps it in `%…%` unless told
     * otherwise through named operands, so every LIKE carries them (#27).
     *
     * @param array<array-key, mixed> $expected
     */
    #[DataProvider('likeConditionProvider')]
    public function visitComparisonLikeOperator(ComparisonSpecification $spec, array $expected): void
    {
        $query = $this->makeQuery();
        $visitor = new QueryBuildingVisitor(query: $query);

        $visitor->visitComparison(specification: $spec);

        Assert::same($query->getWhere(), $expected);
    }

    public static function likeConditionProvider(): iterable
    {
        yield 'like sends the pattern verbatim' => [
            new ComparisonSpecification(column: 'name', value: '%john%', operator: 'like'),
            ['like', 'name', '%john%', 'mode' => LikeMode::Custom, 'escape' => false],
        ];
        yield 'not like sends the pattern verbatim' => [
            ComparisonSpecification::notLike(column: 'name', pattern: 'a_b%'),
            ['not like', 'name', 'a_b%', 'mode' => LikeMode::Custom, 'escape' => false],
        ];
        yield 'ilike is a case-insensitive like' => [
            ComparisonSpecification::ilike(column: 'name', pattern: '%john%'),
            ['like', 'name', '%john%', 'caseSensitive' => false, 'mode' => LikeMode::Custom, 'escape' => false],
        ];
        yield 'not ilike is a case-insensitive not like' => [
            ComparisonSpecification::notIlike(column: 'name', pattern: '%john%'),
            ['not like', 'name', '%john%', 'caseSensitive' => false, 'mode' => LikeMode::Custom, 'escape' => false],
        ];
        yield 'upper-case operator is normalized' => [
            new ComparisonSpecification(column: 'name', value: 'x', operator: 'NOT ILIKE'),
            ['not like', 'name', 'x', 'caseSensitive' => false, 'mode' => LikeMode::Custom, 'escape' => false],
        ];
        yield 'startsWith lets the builder add the wildcard' => [
            ComparisonSpecification::startsWith(column: 'name', prefix: 'ab'),
            ['like', 'name', 'ab', 'mode' => LikeMode::StartsWith],
        ];
        yield 'endsWith lets the builder add the wildcard' => [
            ComparisonSpecification::endsWith(column: 'name', suffix: 'ab'),
            ['like', 'name', 'ab', 'mode' => LikeMode::EndsWith],
        ];
        yield 'contains lets the builder add the wildcards' => [
            ComparisonSpecification::contains(column: 'name', substring: 'ab'),
            ['like', 'name', 'ab', 'mode' => LikeMode::Contains],
        ];
        yield 'a match mode combines with a case-insensitive operator' => [
            new ComparisonSpecification(column: 'name', value: 'ab', operator: 'ilike', likeMatch: LikeMatch::Contains),
            ['like', 'name', 'ab', 'caseSensitive' => false, 'mode' => LikeMode::Contains],
        ];
    }

    public function visitComparisonDateTimeValue(): void
    {
        $query = $this->makeQuery();
        $visitor = new QueryBuildingVisitor(query: $query);
        $spec = ComparisonSpecification::greaterThan(
            column: 'created_at',
            value: new DateTimeImmutable('2024-01-02 03:04:05'),
        );

        $visitor->visitComparison(specification: $spec);

        Assert::same($query->getWhere(), ['>', 'created_at', '2024-01-02 03:04:05']);
    }

    public function visitNotWithComparison(): void
    {
        $query = $this->makeQuery();
        $visitor = new QueryBuildingVisitor(query: $query);
        $innerSpec = new ComparisonSpecification(column: 'status', value: 'active');
        $notSpec = new NotSpecification(specification: $innerSpec);

        $visitor->visitNot(specification: $notSpec);

        $where = $query->getWhere();
        Assert::true(is_array($where));
        Assert::same($where[0], 'not');
    }

    public function visitNotWithDoubleNotUnwraps(): void
    {
        $query = $this->makeQuery();
        $visitor = new QueryBuildingVisitor(query: $query);
        $innerSpec = new ComparisonSpecification(column: 'status', value: 'active');
        $singleNot = new NotSpecification(specification: $innerSpec);
        $doubleNot = new NotSpecification(specification: $singleNot);

        $visitor->visitNot(specification: $doubleNot);

        Assert::same($query->getWhere(), ['=', 'status', 'active']);
    }

    public function visitOrWithEmptySpecifications(): void
    {
        $query = $this->makeQuery();
        $visitor = new QueryBuildingVisitor(query: $query);
        $orSpec = new OrSpecification(specifications: []);

        $visitor->visitOr(specification: $orSpec);

        Assert::null($query->getWhere());
    }

    public function visitOrUsesIsolatedSubQueriesAndMergesParams(): void
    {
        $query = $this->makeQuery();
        $query->where(condition: ['tenant_id' => 1], params: [':tenant_id' => 1]);

        $visitor = new QueryBuildingVisitor(query: $query);
        $visitor->visitOr(specification: OrSpecification::create(
            new RawSpecification(condition: 'status = :active', params: [':active' => 'active']),
            new RawSpecification(condition: 'status = :pending', params: [':pending' => 'pending']),
        ));

        Assert::same(
            $query->getWhere(),
            ['and', ['tenant_id' => 1], ['or', 'status = :active', 'status = :pending']],
        );
        Assert::same(
            $query->getParams(),
            [':tenant_id' => 1, ':active' => 'active', ':pending' => 'pending'],
        );
    }

    public function visitOrRenamesCollidingRawParams(): void
    {
        $query = $this->makeQuery();
        $query->where(condition: ['tenant_id' => 1], params: [':tenant_id' => 1]);

        $visitor = new QueryBuildingVisitor(query: $query);
        $visitor->visitOr(specification: OrSpecification::create(
            new RawSpecification(condition: 'status = :value', params: [':value' => 'active']),
            new RawSpecification(condition: 'status = :value', params: [':value' => 'pending']),
        ));

        Assert::same(
            $query->getWhere(),
            ['and', ['tenant_id' => 1], ['or', 'status = :value', 'status = :value_0']],
        );
        Assert::same(
            $query->getParams(),
            [':tenant_id' => 1, ':value' => 'active', ':value_0' => 'pending'],
        );
    }

    public function visitNotRenamesCollidingRawParams(): void
    {
        $query = $this->makeQuery();
        $query->where(condition: ['tenant_id' => 1], params: [':tenant_id' => 1]);

        $visitor = new QueryBuildingVisitor(query: $query);
        $visitor->visitNot(specification: new NotSpecification(
            specification: new RawSpecification(condition: 'status = :tenant_id', params: [':tenant_id' => 'active']),
        ));

        Assert::same(
            $query->getWhere(),
            ['and', ['tenant_id' => 1], ['not', 'status = :tenant_id_0']],
        );
        Assert::same(
            $query->getParams(),
            [':tenant_id' => 1, ':tenant_id_0' => 'active'],
        );
    }

    public function specificationBuilderOrWhereAppliesAsOrCondition(): void
    {
        $specification = SpecificationBuilder::create()
            ->whereEqual(column: 'status', value: 'active')
            ->orWhere(callback: function (SpecificationBuilder $builder): void {
                $builder->whereEqual(column: 'type', value: 'email')
                    ->whereGreaterThan(column: 'priority', value: 5);
            })
            ->build();

        $query = $this->makeQuery();
        $visitor = new QueryBuildingVisitor(query: $query);
        $visitor->visitComposite(specification: $specification);

        Assert::same(
            $query->getWhere(),
            ['or', ['=', 'status', 'active'], ['and', ['=', 'type', 'email'], ['>', 'priority', 5]]],
        );
    }

    public function visitOrderByDirection(): void
    {
        $query = $this->makeQuery();
        $visitor = new QueryBuildingVisitor(query: $query);
        $spec = new OrderBySpecification(columns: ['created_at' => 'DESC']);

        $visitor->visitOrderBy(specification: $spec);

        Assert::same($query->getOrderBy(), ['created_at' => SORT_DESC]);
    }

    public function visitOrderByInvalidDirection(): void
    {
        Expect::exception(\InvalidArgumentException::class)->withMessageContaining('Invalid order direction "sideways" for column "name"');

        $query = $this->makeQuery();
        $visitor = new QueryBuildingVisitor(query: $query);
        $spec = new OrderBySpecification(columns: ['name' => 'sideways']);
        $visitor->visitOrderBy(specification: $spec);
    }

    public function visitNotWithEmptyCompositeThrows(): void
    {
        Expect::exception(\InvalidArgumentException::class);

        $query = $this->makeQuery();
        $visitor = new QueryBuildingVisitor(query: $query);
        $notSpec = new NotSpecification(specification: new CompositeSpecification(specifications: []));
        $visitor->visitNot(specification: $notSpec);
    }

    public function visitOrderByCustom(): void
    {
        $query = $this->makeQuery();
        $visitor = new QueryBuildingVisitor(query: $query);
        $spec = new OrderBySpecification(columns: ['name' => 'ASC']);

        $visitor->visitOrderBy(specification: $spec);

        Assert::same($query->getOrderBy(), ['name' => SORT_ASC]);
    }

    public function visitOrderByMultipleColumns(): void
    {
        $query = $this->makeQuery();
        $visitor = new QueryBuildingVisitor(query: $query);
        $spec = new OrderBySpecification(columns: ['sort' => 'DESC', 'name' => 'ASC']);

        $visitor->visitOrderBy(specification: $spec);

        Assert::same($query->getOrderBy(), ['sort' => SORT_DESC, 'name' => SORT_ASC]);
    }

    public function visitCompositeWithEmptySpecifications(): void
    {
        $query = $this->makeQuery();
        $visitor = new QueryBuildingVisitor(query: $query);
        $composite = new CompositeSpecification(specifications: []);

        $visitor->visitComposite(specification: $composite);

        Assert::null($query->getWhere());
    }

    public function visitCompositeWithMultipleSpecifications(): void
    {
        $query = $this->makeQuery();
        $visitor = new QueryBuildingVisitor(query: $query);
        $spec1 = new ComparisonSpecification(column: 'status', value: 'active');
        $spec2 = new ComparisonSpecification(column: 'age', value: 18, operator: '>');
        $composite = new CompositeSpecification(specifications: [$spec1, $spec2]);

        $visitor->visitComposite(specification: $composite);

        Assert::same($query->getWhere(), ['and', ['=', 'status', 'active'], ['>', 'age', 18]]);
    }

    public function visitComparisonInWithDateTimeValues(): void
    {
        $query = $this->makeQuery();
        $visitor = new QueryBuildingVisitor(query: $query);
        $dt1 = new DateTimeImmutable('2024-01-01');
        $dt2 = new DateTimeImmutable('2024-06-01');

        $spec = new ComparisonSpecification(column: 'date_col', value: [$dt1, $dt2], operator: 'in');
        $visitor->visitComparison(specification: $spec);

        Assert::same($query->getWhere(), ['in', 'date_col', ['2024-01-01 00:00:00', '2024-06-01 00:00:00']]);
    }

    public function visitOrderByWithLowercaseDirectionStrings(): void
    {
        $query = $this->makeQuery();
        $visitor = new QueryBuildingVisitor(query: $query);
        $spec = new OrderBySpecification(columns: ['col_a' => 'asc', 'col_b' => 'desc']);

        $visitor->visitOrderBy(specification: $spec);

        Assert::same($query->getOrderBy(), ['col_a' => SORT_ASC, 'col_b' => SORT_DESC]);
    }

    public function visitOrderByWithIntDirectionTriggersContinue(): void
    {
        $query = $this->makeQuery();
        $visitor = new QueryBuildingVisitor(query: $query);
        $spec = new OrderBySpecification(columns: ['col_a' => SORT_ASC, 'col_b' => SORT_DESC]);

        $visitor->visitOrderBy(specification: $spec);

        Assert::same($query->getOrderBy(), ['col_a' => SORT_ASC, 'col_b' => SORT_DESC]);
    }

    public function visitLimitAppliesLimitToQuery(): void
    {
        $query = $this->makeQuery();
        $visitor = new QueryBuildingVisitor(query: $query);

        $visitor->visitLimit(specification: new LimitSpecification(limit: 42));

        Assert::same($query->getLimit(), 42);
    }

    public function visitOffsetAppliesOffsetToQuery(): void
    {
        $query = $this->makeQuery();
        $visitor = new QueryBuildingVisitor(query: $query);

        $visitor->visitOffset(specification: new OffsetSpecification(offset: 10));

        Assert::same($query->getOffset(), 10);
    }

    public function visitOrWithSingleConditionFlattensToAndWhere(): void
    {
        $query = $this->makeQuery();
        $visitor = new QueryBuildingVisitor(query: $query);

        $visitor->visitOr(specification: OrSpecification::create(
            new ComparisonSpecification(column: 'status', value: 'active'),
        ));

        $where = $query->getWhere();
        Assert::notNull($where);
        Assert::same($where, ['=', 'status', 'active']);
    }

    public function normalizePlaceholderAddsColonPrefix(): void
    {
        $query = $this->makeQuery();
        $query->where(condition: ['a' => 1], params: [':value' => 1]);

        $visitor = new QueryBuildingVisitor(query: $query);
        $visitor->visitOr(specification: OrSpecification::create(
            new RawSpecification(condition: 'b = :value', params: ['value' => 2]),
        ));

        $params = $query->getParams();
        Assert::true(array_key_exists(':value', $params));
        Assert::same($params[':value'], 1);
        Assert::true(array_key_exists(':value_0', $params));
        Assert::same($params[':value_0'], 2);
    }

    public function replacePlaceholdersInArrayCondition(): void
    {
        $query = $this->makeQuery();
        $query->where(condition: ['p' => 1], params: [':p' => 1]);

        $visitor = new QueryBuildingVisitor(query: $query);
        $visitor->visitNot(specification: new NotSpecification(
            specification: new RawSpecification(
                condition: ['and', 'x = :p', 'y = :p'],
                params: [':p' => 2],
            ),
        ));

        $where = $query->getWhere();
        Assert::same(
            $where,
            ['and', ['p' => 1], ['not', ['and', 'x = :p_0', 'y = :p_0']]],
        );
    }

    public function visitOrSkipsEmptyAndProcessesRemaining(): void
    {
        $query = $this->makeQuery();
        $visitor = new QueryBuildingVisitor(query: $query);

        $visitor->visitOr(specification: new OrSpecification(specifications: [
            new CompositeSpecification(specifications: []),
            new ComparisonSpecification(column: 'status', value: 'active'),
        ]));

        Assert::same($query->getWhere(), ['=', 'status', 'active']);
    }

    public function orWherePreservesOriginalBuilderState(): void
    {
        $builder = SpecificationBuilder::create()
            ->whereEqual(column: 'status', value: 'active');

        $modified = $builder->orWhere(callback: function (SpecificationBuilder $b): void {
            $b->whereEqual(column: 'type', value: 'email');
        });

        $originalSpecs = $builder->build()->getSpecifications();
        Assert::count($originalSpecs, 1);

        $modifiedSpecs = $modified->build()->getSpecifications();
        Assert::count($modifiedSpecs, 1);
        Assert::instanceOf($modifiedSpecs[0], OrSpecification::class);
    }

    #[Property(runs: 300)]
    public function scalarComparisonBuildsOperatorColumnValueTriple(string $column, string $operator, int $value): void
    {
        $query = $this->makeQuery();
        (new ComparisonSpecification(column: $column, value: $value, operator: $operator))
            ->accept(new QueryBuildingVisitor(query: $query));

        Assert::same($query->getWhere(), [$operator, $column, $value]);
    }

    /** @return array<string, ArbitraryInterface> */
    public static function scalarComparisonBuildsOperatorColumnValueTripleGenerators(): array
    {
        return [
            'column' => Gen::oneOf('age', 'price', 'user_id', 'score', 'created_at'),
            'operator' => Gen::oneOf('=', '!=', '<>', '>', '>=', '<', '<='),
            'value' => Gen::int(),
        ];
    }

    #[Property(runs: 300)]
    public function betweenBuildsConditionWithBothBounds(string $column, int $from, int $to): void
    {
        $query = $this->makeQuery();
        (new ComparisonSpecification(column: $column, value: [$from, $to], operator: 'between'))
            ->accept(new QueryBuildingVisitor(query: $query));

        Assert::same($query->getWhere(), ['between', $column, $from, $to]);
    }

    /** @return array<string, ArbitraryInterface> */
    public static function betweenBuildsConditionWithBothBoundsGenerators(): array
    {
        return [
            'column' => Gen::oneOf('age', 'price', 'score', 'ts'),
            'from' => Gen::int(),
            'to' => Gen::int(),
        ];
    }

    #[Property(runs: 300)]
    public function doubleNegationCollapsesToPlainCondition(string $column, int $value): void
    {
        $plain = $this->makeQuery();
        $spec = new ComparisonSpecification(column: $column, value: $value, operator: '=');
        $spec->accept(new QueryBuildingVisitor(query: $plain));

        $doubleNot = $this->makeQuery();
        (new NotSpecification(specification: new NotSpecification(specification: $spec)))
            ->accept(new QueryBuildingVisitor(query: $doubleNot));

        Assert::same($doubleNot->getWhere(), $plain->getWhere());
    }

    /** @return array<string, ArbitraryInterface> */
    public static function doubleNegationCollapsesToPlainConditionGenerators(): array
    {
        return [
            'column' => Gen::oneOf('age', 'status', 'price'),
            'value' => Gen::int(),
        ];
    }
}
