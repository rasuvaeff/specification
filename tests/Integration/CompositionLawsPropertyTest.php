<?php

declare(strict_types=1);

namespace Rasuvaeff\Specification\Tests\Integration;

use Rasuvaeff\PropertyTesting\ArbitraryInterface;
use Rasuvaeff\PropertyTesting\Gen;
use Rasuvaeff\PropertyTesting\Property;
use Rasuvaeff\Specification\ComparisonSpecification;
use Rasuvaeff\Specification\CompositeSpecification;
use Rasuvaeff\Specification\NotSpecification;
use Rasuvaeff\Specification\OrSpecification;
use Rasuvaeff\Specification\QueryApplier;
use Rasuvaeff\Specification\Specification;
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
 * Property-based tests for the composition algebra (AND/OR/NOT). Two
 * specification trees are compared not by structure, but by the row set they
 * select on a fixed in-memory SQLite table via {@see QueryApplier} — the
 * algebraic laws are about observable query behaviour, not about a `toSql()`
 * representation the package does not have.
 */
#[Test]
#[CoversNothing]
final class CompositionLawsPropertyTest
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
    private function ids(Specification $spec): array
    {
        $query = (new Query($this->db))->select('id')->from('items');
        QueryApplier::apply(specification: $spec, query: $query);

        $ids = array_map(static fn(array|object $row): int => (int) ((array) $row)['id'], $query->all());
        sort($ids);

        return $ids;
    }

    /**
     * @return list<ComparisonSpecification>
     */
    private static function leaves(): array
    {
        return [
            ComparisonSpecification::equal(column: 'status', value: 'active'),
            ComparisonSpecification::equal(column: 'status', value: 'inactive'),
            ComparisonSpecification::greaterThan(column: 'price', value: 20),
            ComparisonSpecification::greaterThanOrEqual(column: 'price', value: 20),
            ComparisonSpecification::lessThan(column: 'price', value: 40),
            ComparisonSpecification::between(column: 'price', from: 20, to: 40),
            ComparisonSpecification::startsWith(column: 'name', prefix: 'a'),
            ComparisonSpecification::in(column: 'name', values: ['alpha', 'bravo']),
            ComparisonSpecification::notEqual(column: 'id', value: 3),
            ComparisonSpecification::greaterThanOrEqual(column: 'created_at', value: '2024-03-01'),
        ];
    }

    /**
     * A leaf comparison, or that comparison wrapped in NOT with equal odds —
     * exercises the visitor's subquery/param-isolation path for negated
     * operands, not just plain comparisons.
     */
    private static function operand(): ArbitraryInterface
    {
        return Gen::recursive(
            leaf: Gen::elements(values: self::leaves()),
            wrap: static fn(ArbitraryInterface $inner): ArbitraryInterface => Gen::map(
                inner: $inner,
                map: static fn(ComparisonSpecification $spec): NotSpecification => NotSpecification::create(specification: $spec),
            ),
            maxDepth: 1,
        );
    }

    #[Property(runs: 150)]
    public function andIsCommutative(Specification $a, Specification $b): void
    {
        $lhs = CompositeSpecification::create()->withSpecification($a)->withSpecification($b);
        $rhs = CompositeSpecification::create()->withSpecification($b)->withSpecification($a);

        Assert::same($this->ids($lhs), $this->ids($rhs));
    }

    /** @return array<string, ArbitraryInterface> */
    public static function andIsCommutativeGenerators(): array
    {
        return ['a' => self::operand(), 'b' => self::operand()];
    }

    #[Property(runs: 100)]
    public function andIsAssociative(Specification $a, Specification $b, Specification $c): void
    {
        $lhs = CompositeSpecification::create()
            ->withSpecification(CompositeSpecification::create()->withSpecification($a)->withSpecification($b))
            ->withSpecification($c);
        $rhs = CompositeSpecification::create()
            ->withSpecification($a)
            ->withSpecification(CompositeSpecification::create()->withSpecification($b)->withSpecification($c));

        Assert::same($this->ids($lhs), $this->ids($rhs));
    }

    /** @return array<string, ArbitraryInterface> */
    public static function andIsAssociativeGenerators(): array
    {
        return ['a' => self::operand(), 'b' => self::operand(), 'c' => self::operand()];
    }

    #[Property(runs: 150)]
    public function orIsCommutative(Specification $a, Specification $b): void
    {
        Assert::same(
            $this->ids(OrSpecification::create($a, $b)),
            $this->ids(OrSpecification::create($b, $a)),
        );
    }

    /** @return array<string, ArbitraryInterface> */
    public static function orIsCommutativeGenerators(): array
    {
        return ['a' => self::operand(), 'b' => self::operand()];
    }

    #[Property(runs: 100)]
    public function orIsAssociative(Specification $a, Specification $b, Specification $c): void
    {
        $lhs = OrSpecification::create(OrSpecification::create($a, $b), $c);
        $rhs = OrSpecification::create($a, OrSpecification::create($b, $c));

        Assert::same($this->ids($lhs), $this->ids($rhs));
    }

    /** @return array<string, ArbitraryInterface> */
    public static function orIsAssociativeGenerators(): array
    {
        return ['a' => self::operand(), 'b' => self::operand(), 'c' => self::operand()];
    }

    #[Property(runs: 150)]
    public function andIsIdempotent(Specification $a): void
    {
        $duplicated = CompositeSpecification::create()->withSpecification($a)->withSpecification($a);

        Assert::same($this->ids($duplicated), $this->ids($a));
    }

    /** @return array<string, ArbitraryInterface> */
    public static function andIsIdempotentGenerators(): array
    {
        return ['a' => self::operand()];
    }

    #[Property(runs: 150)]
    public function deMorganAnd(Specification $a, Specification $b): void
    {
        $lhs = NotSpecification::create(
            specification: CompositeSpecification::create()->withSpecification($a)->withSpecification($b),
        );
        $rhs = OrSpecification::create(NotSpecification::create($a), NotSpecification::create($b));

        Assert::same($this->ids($lhs), $this->ids($rhs));
    }

    /** @return array<string, ArbitraryInterface> */
    public static function deMorganAndGenerators(): array
    {
        return ['a' => self::operand(), 'b' => self::operand()];
    }

    #[Property(runs: 150)]
    public function deMorganOr(Specification $a, Specification $b): void
    {
        $lhs = NotSpecification::create(specification: OrSpecification::create($a, $b));
        $rhs = CompositeSpecification::create()
            ->withSpecification(NotSpecification::create($a))
            ->withSpecification(NotSpecification::create($b));

        Assert::same($this->ids($lhs), $this->ids($rhs));
    }

    /** @return array<string, ArbitraryInterface> */
    public static function deMorganOrGenerators(): array
    {
        return ['a' => self::operand(), 'b' => self::operand()];
    }

    #[Property(runs: 100)]
    public function distributive(Specification $a, Specification $b, Specification $c): void
    {
        $lhs = CompositeSpecification::create()
            ->withSpecification($a)
            ->withSpecification(OrSpecification::create($b, $c));
        $rhs = OrSpecification::create(
            CompositeSpecification::create()->withSpecification($a)->withSpecification($b),
            CompositeSpecification::create()->withSpecification($a)->withSpecification($c),
        );

        Assert::same($this->ids($lhs), $this->ids($rhs));
    }

    /** @return array<string, ArbitraryInterface> */
    public static function distributiveGenerators(): array
    {
        return ['a' => self::operand(), 'b' => self::operand(), 'c' => self::operand()];
    }
}
