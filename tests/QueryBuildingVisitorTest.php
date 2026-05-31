<?php

declare(strict_types=1);

namespace Rasuvaeff\Specification\Tests;

use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Rasuvaeff\Specification\ComparisonSpecification;
use Rasuvaeff\Specification\CompositeSpecification;
use Rasuvaeff\Specification\NotSpecification;
use Rasuvaeff\Specification\OrConditionSpecification;
use Rasuvaeff\Specification\OrderBySpecification;
use Rasuvaeff\Specification\OrSpecification;
use Rasuvaeff\Specification\QueryBuildingVisitor;
use Rasuvaeff\Specification\RawSpecification;
use Rasuvaeff\Specification\SpecificationBuilder;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Query\Query;
use Yiisoft\Db\Query\QueryInterface;

use const SORT_ASC;
use const SORT_DESC;

#[CoversClass(QueryBuildingVisitor::class)]
final class QueryBuildingVisitorTest extends TestCase
{
    private MockObject&QueryInterface $queryMock;

    private QueryBuildingVisitor $visitor;

    #[\Override]
    protected function setUp(): void
    {
        $this->queryMock = $this->createMock(QueryInterface::class);
        $this->visitor = new QueryBuildingVisitor(query: $this->queryMock);
    }

    private function createQuery(): Query
    {
        return new Query(db: $this->createMock(ConnectionInterface::class));
    }

    #[Test]
    public function visitComparisonSimpleOperator(): void
    {
        $spec = new ComparisonSpecification(column: 'age', value: 25, operator: '>');

        $this->queryMock->expects($this->once())
            ->method('andWhere')
            ->with(['>', 'age', 25]);

        $this->visitor->visitComparison(specification: $spec);
    }

    #[Test]
    public function visitComparisonBetweenOperator(): void
    {
        $spec = new ComparisonSpecification(column: 'age', value: [18, 65], operator: 'between');

        $this->queryMock->expects($this->once())
            ->method('andWhere')
            ->with(['between', 'age', 18, 65]);

        $this->visitor->visitComparison(specification: $spec);
    }

    #[Test]
    public function visitComparisonBetweenInvalidArray(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Operator "between" requires array with exactly two values');

        $spec = new ComparisonSpecification(column: 'age', value: [18], operator: 'between');
        $this->visitor->visitComparison(specification: $spec);
    }

    #[Test]
    public function visitComparisonInOperator(): void
    {
        $spec = new ComparisonSpecification(column: 'status', value: ['active', 'pending'], operator: 'in');

        $this->queryMock->expects($this->once())
            ->method('andWhere')
            ->with(['in', 'status', ['active', 'pending']]);

        $this->visitor->visitComparison(specification: $spec);
    }

    #[Test]
    public function visitComparisonIsOperator(): void
    {
        $spec = new ComparisonSpecification(column: 'deleted_at', value: null, operator: 'is');

        $this->queryMock->expects($this->once())
            ->method('andWhere')
            ->with(['=', 'deleted_at', null]);

        $this->visitor->visitComparison(specification: $spec);
    }

    #[Test]
    public function visitComparisonIsNotOperator(): void
    {
        $spec = new ComparisonSpecification(column: 'deleted_at', value: null, operator: 'is not');

        $this->queryMock->expects($this->once())
            ->method('andWhere')
            ->with(['!=', 'deleted_at', null]);

        $this->visitor->visitComparison(specification: $spec);
    }

    #[Test]
    public function visitComposite(): void
    {
        $spec1 = new ComparisonSpecification(column: 'status', value: 'active');
        $spec2 = new ComparisonSpecification(column: 'age', value: 18, operator: '>');
        $composite = new CompositeSpecification(specifications: [$spec1, $spec2]);

        $this->queryMock->expects($this->exactly(2))
            ->method('andWhere');

        $this->visitor->visitComposite(specification: $composite);
    }

    #[Test]
    public function visitOrCondition(): void
    {
        $spec = new OrConditionSpecification(conditions: [
            ['status' => 'active'],
            ['>', 'age', 18],
        ]);

        $this->queryMock->expects($this->once())
            ->method('andWhere')
            ->with(['or', ['status' => 'active'], ['>', 'age', 18]]);

        $this->visitor->visitOrCondition(specification: $spec);
    }

    #[Test]
    public function visitOrConditionEmpty(): void
    {
        $spec = new OrConditionSpecification(conditions: []);

        $this->queryMock->expects($this->never())
            ->method('andWhere');

        $this->visitor->visitOrCondition(specification: $spec);
    }

    #[Test]
    public function visitRawStringCondition(): void
    {
        $spec = new RawSpecification(condition: 'age > :age', params: [':age' => 18]);

        $this->queryMock->expects($this->once())
            ->method('andWhere')
            ->with('age > :age', [':age' => 18]);

        $this->visitor->visitRaw(specification: $spec);
    }

    #[Test]
    public function visitRawArrayCondition(): void
    {
        $condition = ['or', ['a' => 1], ['b' => 2]];
        $params = ['a' => 1, 'b' => 2];
        $spec = new RawSpecification(condition: $condition, params: $params);

        $this->queryMock->expects($this->once())
            ->method('andWhere')
            ->with($condition, $params);

        $this->visitor->visitRaw(specification: $spec);
    }

    #[Test]
    public function visitComparisonNotBetweenOperator(): void
    {
        $spec = new ComparisonSpecification(column: 'age', value: [18, 65], operator: 'not between');

        $this->queryMock->expects($this->once())
            ->method('andWhere')
            ->with(['not between', 'age', 18, 65]);

        $this->visitor->visitComparison(specification: $spec);
    }

    #[Test]
    public function visitComparisonNotInOperator(): void
    {
        $spec = new ComparisonSpecification(column: 'status', value: ['banned', 'deleted'], operator: 'not in');

        $this->queryMock->expects($this->once())
            ->method('andWhere')
            ->with(['not in', 'status', ['banned', 'deleted']]);

        $this->visitor->visitComparison(specification: $spec);
    }

    #[Test]
    public function visitComparisonNotBetweenInvalidArray(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('requires array with exactly two values');

        $spec = new ComparisonSpecification(column: 'age', value: [18], operator: 'not between');
        $this->visitor->visitComparison(specification: $spec);
    }

    #[Test]
    public function visitComparisonNotInInvalidValue(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('requires array value');

        $spec = new ComparisonSpecification(column: 'age', value: 'not_array', operator: 'not in');
        $this->visitor->visitComparison(specification: $spec);
    }

    #[Test]
    public function visitComparisonDefaultOperator(): void
    {
        $spec = new ComparisonSpecification(column: 'name', value: 'John', operator: '!=');

        $this->queryMock->expects($this->once())
            ->method('andWhere')
            ->with(['!=', 'name', 'John']);

        $this->visitor->visitComparison(specification: $spec);
    }

    #[Test]
    public function visitComparisonLikeOperator(): void
    {
        $spec = new ComparisonSpecification(column: 'name', value: '%john%', operator: 'like');

        $this->queryMock->expects($this->once())
            ->method('andWhere')
            ->with(['like', 'name', '%john%']);

        $this->visitor->visitComparison(specification: $spec);
    }

    #[Test]
    public function visitComparisonDateTimeValue(): void
    {
        $spec = ComparisonSpecification::greaterThan(
            column: 'created_at',
            value: new DateTimeImmutable('2024-01-02 03:04:05'),
        );

        $this->queryMock->expects($this->once())
            ->method('andWhere')
            ->with(['>', 'created_at', '2024-01-02 03:04:05']);

        $this->visitor->visitComparison(specification: $spec);
    }

    #[Test]
    public function visitNotWithComparison(): void
    {
        $innerSpec = new ComparisonSpecification(column: 'status', value: 'active');
        $notSpec = new NotSpecification(specification: $innerSpec);

        $queryMock = $this->createMock(QueryInterface::class);
        $queryMock->method('getWhere')->willReturn(['=', 'status', 'active']);

        /** @var list<array<mixed>> $andWhereCalls */
        $andWhereCalls = [];
        $queryMock->method('andWhere')
            ->willReturnCallback(function (array $condition) use ($queryMock, &$andWhereCalls): QueryInterface {
                $andWhereCalls[] = $condition;

                return $queryMock;
            });

        $visitor = new QueryBuildingVisitor(query: $queryMock);
        $visitor->visitNot(specification: $notSpec);

        $notConditions = array_filter($andWhereCalls, static fn(array $c): bool => ($c[0] ?? null) === 'not');
        $this->assertCount(1, $notConditions);
    }

    #[Test]
    public function visitNotWithDoubleNotUnwraps(): void
    {
        $innerSpec = new ComparisonSpecification(column: 'status', value: 'active');
        $singleNot = new NotSpecification(specification: $innerSpec);
        $doubleNot = new NotSpecification(specification: $singleNot);

        $this->queryMock->expects($this->once())
            ->method('andWhere')
            ->with(['=', 'status', 'active']);

        $this->visitor->visitNot(specification: $doubleNot);
    }

    #[Test]
    public function visitOrWithEmptySpecifications(): void
    {
        $orSpec = new OrSpecification(specifications: []);

        $this->queryMock->expects($this->never())
            ->method('andWhere');

        $this->visitor->visitOr(specification: $orSpec);
    }

    #[Test]
    public function visitOrUsesIsolatedSubQueriesAndMergesParams(): void
    {
        $query = $this->createQuery();
        $query->where(condition: ['tenant_id' => 1], params: [':tenant_id' => 1]);

        $visitor = new QueryBuildingVisitor(query: $query);
        $visitor->visitOr(specification: OrSpecification::create(
            new RawSpecification(condition: 'status = :active', params: [':active' => 'active']),
            new RawSpecification(condition: 'status = :pending', params: [':pending' => 'pending']),
        ));

        $this->assertSame(
            ['and', ['tenant_id' => 1], ['or', 'status = :active', 'status = :pending']],
            $query->getWhere(),
        );
        $this->assertSame(
            [':tenant_id' => 1, ':active' => 'active', ':pending' => 'pending'],
            $query->getParams(),
        );
    }

    #[Test]
    public function visitOrRenamesCollidingRawParams(): void
    {
        $query = $this->createQuery();
        $query->where(condition: ['tenant_id' => 1], params: [':tenant_id' => 1]);

        $visitor = new QueryBuildingVisitor(query: $query);
        $visitor->visitOr(specification: OrSpecification::create(
            new RawSpecification(condition: 'status = :value', params: [':value' => 'active']),
            new RawSpecification(condition: 'status = :value', params: [':value' => 'pending']),
        ));

        $this->assertSame(
            ['and', ['tenant_id' => 1], ['or', 'status = :value', 'status = :value_0']],
            $query->getWhere(),
        );
        $this->assertSame(
            [':tenant_id' => 1, ':value' => 'active', ':value_0' => 'pending'],
            $query->getParams(),
        );
    }

    #[Test]
    public function visitNotRenamesCollidingRawParams(): void
    {
        $query = $this->createQuery();
        $query->where(condition: ['tenant_id' => 1], params: [':tenant_id' => 1]);

        $visitor = new QueryBuildingVisitor(query: $query);
        $visitor->visitNot(specification: new NotSpecification(
            specification: new RawSpecification(condition: 'status = :tenant_id', params: [':tenant_id' => 'active']),
        ));

        $this->assertSame(
            ['and', ['tenant_id' => 1], ['not', 'status = :tenant_id_0']],
            $query->getWhere(),
        );
        $this->assertSame(
            [':tenant_id' => 1, ':tenant_id_0' => 'active'],
            $query->getParams(),
        );
    }

    #[Test]
    public function specificationBuilderOrWhereAppliesAsOrCondition(): void
    {
        $specification = SpecificationBuilder::create()
            ->whereEqual(column: 'status', value: 'active')
            ->orWhere(callback: function (SpecificationBuilder $builder): void {
                $builder->whereEqual(column: 'type', value: 'email')
                    ->whereGreaterThan(column: 'priority', value: 5);
            })
            ->build();

        $query = $this->createQuery();
        $visitor = new QueryBuildingVisitor(query: $query);
        $visitor->visitComposite(specification: $specification);

        $this->assertSame(
            ['or', ['=', 'status', 'active'], ['and', ['=', 'type', 'email'], ['>', 'priority', 5]]],
            $query->getWhere(),
        );
    }

    #[Test]
    public function visitOrderByDirection(): void
    {
        $spec = new OrderBySpecification(columns: ['created_at' => 'DESC']);

        $this->queryMock->expects($this->once())
            ->method('addOrderBy')
            ->with(['created_at' => SORT_DESC]);

        $this->visitor->visitOrderBy(specification: $spec);
    }

    #[Test]
    public function visitOrderByInvalidDirection(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid order direction "sideways" for column "name"');

        $spec = new OrderBySpecification(columns: ['name' => 'sideways']);
        $this->visitor->visitOrderBy(specification: $spec);
    }

    #[Test]
    public function visitNotWithEmptyCompositeThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $notSpec = new NotSpecification(specification: new CompositeSpecification(specifications: []));
        $this->visitor->visitNot(specification: $notSpec);
    }

    #[Test]
    public function visitOrderByCustom(): void
    {
        $spec = new OrderBySpecification(columns: ['name' => 'ASC']);

        $this->queryMock->expects($this->once())
            ->method('addOrderBy')
            ->with(['name' => SORT_ASC]);

        $this->visitor->visitOrderBy(specification: $spec);
    }

    #[Test]
    public function visitOrderByMultipleColumns(): void
    {
        $spec = new OrderBySpecification(columns: ['sort' => 'DESC', 'name' => 'ASC']);

        $this->queryMock->expects($this->once())
            ->method('addOrderBy')
            ->with(['sort' => SORT_DESC, 'name' => SORT_ASC]);

        $this->visitor->visitOrderBy(specification: $spec);
    }

    #[Test]
    public function visitCompositeWithEmptySpecifications(): void
    {
        $composite = new CompositeSpecification(specifications: []);

        $this->queryMock->expects($this->never())
            ->method('andWhere');

        $this->visitor->visitComposite(specification: $composite);
    }

    #[Test]
    public function visitCompositeWithMultipleSpecifications(): void
    {
        $spec1 = new ComparisonSpecification(column: 'status', value: 'active');
        $spec2 = new ComparisonSpecification(column: 'age', value: 18, operator: '>');
        $composite = new CompositeSpecification(specifications: [$spec1, $spec2]);

        $this->queryMock->method('andWhere')->willReturn($this->queryMock);

        $expectedCalls = [
            ['=', 'status', 'active'],
            ['>', 'age', 18],
        ];

        $callIndex = 0;
        $this->queryMock->expects($this->exactly(2))
            ->method('andWhere')
            ->willReturnCallback(function (array $condition) use (&$callIndex, $expectedCalls): MockObject&QueryInterface {
                assert($callIndex < count($expectedCalls));
                $this->assertSame($expectedCalls[$callIndex], $condition);
                $callIndex++;

                return $this->queryMock;
            });

        $this->visitor->visitComposite(specification: $composite);
    }

    #[Test]
    public function visitComparisonInWithDateTimeValues(): void
    {
        $dt1 = new DateTimeImmutable('2024-01-01');
        $dt2 = new DateTimeImmutable('2024-06-01');

        $this->queryMock->expects($this->once())
            ->method('andWhere')
            ->with(['in', 'date_col', ['2024-01-01 00:00:00', '2024-06-01 00:00:00']]);

        $spec = new ComparisonSpecification(column: 'date_col', value: [$dt1, $dt2], operator: 'in');
        $this->visitor->visitComparison(specification: $spec);
    }

    #[Test]
    public function visitOrderByWithLowercaseDirectionStrings(): void
    {
        $this->queryMock->expects($this->once())
            ->method('addOrderBy')
            ->with(['col_a' => SORT_ASC, 'col_b' => SORT_DESC]);

        $spec = new OrderBySpecification(columns: ['col_a' => 'asc', 'col_b' => 'desc']);
        $this->visitor->visitOrderBy(specification: $spec);
    }

    #[Test]
    public function visitOrderByWithIntDirectionTriggersContinue(): void
    {
        $this->queryMock->expects($this->once())
            ->method('addOrderBy')
            ->with(['col_a' => SORT_ASC, 'col_b' => SORT_DESC]);

        $spec = new OrderBySpecification(columns: ['col_a' => SORT_ASC, 'col_b' => SORT_DESC]);
        $this->visitor->visitOrderBy(specification: $spec);
    }
}
