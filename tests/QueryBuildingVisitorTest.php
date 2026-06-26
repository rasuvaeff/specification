<?php

declare(strict_types=1);

namespace Rasuvaeff\Specification\Tests;

use DateTimeImmutable;
use InvalidArgumentException;
use Rasuvaeff\Specification\ComparisonSpecification;
use Rasuvaeff\Specification\CompositeSpecification;
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
use Testo\Expect;
use Testo\Test;
use Yiisoft\Db\Query\Query;

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
        Assert::same($query->getParams(), $params);
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

    public function visitComparisonLikeOperator(): void
    {
        $query = $this->makeQuery();
        $visitor = new QueryBuildingVisitor(query: $query);
        $spec = new ComparisonSpecification(column: 'name', value: '%john%', operator: 'like');

        $visitor->visitComparison(specification: $spec);

        Assert::same($query->getWhere(), ['like', 'name', '%john%']);
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
}
