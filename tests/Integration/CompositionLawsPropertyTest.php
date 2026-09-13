<?php

declare(strict_types=1);

namespace Rasuvaeff\Specification\Tests\Integration;

use Rasuvaeff\PropertyTesting\ArbitraryInterface;
use Rasuvaeff\PropertyTesting\Classify;
use Rasuvaeff\PropertyTesting\Gen;
use Rasuvaeff\PropertyTesting\Property;
use Rasuvaeff\Specification\ComparisonSpecification;
use Rasuvaeff\Specification\CompositeSpecification;
use Rasuvaeff\Specification\NotSpecification;
use Rasuvaeff\Specification\OrSpecification;
use Rasuvaeff\Specification\QueryApplier;
use Rasuvaeff\Specification\RawSpecification;
use Rasuvaeff\Specification\Specification;
use Testo\Assert;
use Testo\Codecov\CoversNothing;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;
use Yiisoft\Cache\ArrayCache;
use Yiisoft\Db\Cache\SchemaCache;
use Yiisoft\Db\Expression\ExpressionInterface;
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
     * The raw leaves all bind through named placeholders and deliberately
     * share `:price` — two of them under one AND, or one of them next to a
     * comparison inside NOT/OR, exercise the placeholder remap that issues
     * #30 and #31 found broken. Comparison leaves bind positionally at build
     * time and never collide by name.
     *
     * @return list<Specification>
     */
    private static function leaves(): array
    {
        return [
            new RawSpecification(condition: 'price > :price', params: [':price' => 20]),
            new RawSpecification(condition: 'price < :price', params: [':price' => 40]),
            new RawSpecification(condition: 'price BETWEEN :price AND :price_max', params: [':price' => 15, ':price_max' => 45]),
            new RawSpecification(condition: 'status = :status', params: ['status' => 'active']),
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
                map: static fn(Specification $spec): NotSpecification => NotSpecification::create(specification: $spec),
            ),
            maxDepth: 1,
        );
    }

    /**
     * Named operands reused by the {@see *Examples()} methods below. The
     * property engine runs each example tuple as an explicit call of the
     * property body *before* the random phase, so a regression that breaks
     * the law on a known edge case fails the test deterministically instead
     * of waiting for 100+ random trials to hit it.
     *
     * Mutation proofs (issue #22) showed that dangerous regressions cluster
     * around NOT-wrapped operands and empty-result intersections; the
     * examples pin exactly those shapes.
     *
     * @return array<string, Specification>
     */
    private static function operandCatalog(): array
    {
        $active = ComparisonSpecification::equal(column: 'status', value: 'active');

        return [
            'active' => $active, // ids 1, 2, 4
            'inactive' => ComparisonSpecification::equal(column: 'status', value: 'inactive'), // ids 3, 5
            'pricey' => ComparisonSpecification::greaterThan(column: 'price', value: 20), // ids 3, 4, 5
            'all' => ComparisonSpecification::greaterThanOrEqual(column: 'id', value: 1), // ids 1..5
            'none' => ComparisonSpecification::lessThan(column: 'id', value: 1), // []
            'notActive' => NotSpecification::create(specification: $active), // ids 3, 5
            'doubleNeg' => NotSpecification::create(specification: NotSpecification::create(specification: $active)), // ids 1, 2, 4
            'rawAbove' => new RawSpecification(condition: 'price > :price', params: [':price' => 20]), // ids 3, 4, 5
            'rawBelow' => new RawSpecification(condition: 'price < :price', params: [':price' => 40]), // ids 1, 2, 3
            'rawRange' => new RawSpecification(condition: 'price BETWEEN :price AND :price_max', params: [':price' => 15, ':price_max' => 45]), // ids 2, 3, 4
        ];
    }

    /**
     * An arbitrary AND/OR/NOT tree over {@see leaves()} for invariants that
     * hold for every specification rather than for a law between two. Raw and
     * comparison leaves are drawn with equal odds, and the root is always a
     * combinator, so that two raw leaves sharing `:price` meet in one query
     * often enough to gate on.
     */
    private static function tree(): ArbitraryInterface
    {
        $leaves = self::leaves();
        $rawLeaves = array_values(array_filter($leaves, static fn(Specification $leaf): bool => $leaf instanceof RawSpecification));
        $comparisonLeaves = array_values(array_filter($leaves, static fn(Specification $leaf): bool => !$leaf instanceof RawSpecification));
        $leaf = Gen::frequency([[1, Gen::elements(values: $rawLeaves)], [1, Gen::elements(values: $comparisonLeaves)]]);

        return self::combine(Gen::recursive(leaf: $leaf, wrap: self::combine(...), maxDepth: 2));
    }

    private static function combine(ArbitraryInterface $inner): ArbitraryInterface
    {
        return Gen::frequency([
            [1, Gen::map(
                inner: $inner,
                map: static fn(Specification $spec): NotSpecification => NotSpecification::create(specification: $spec),
            )],
            [2, Gen::map(
                inner: Gen::tuple($inner, $inner),
                map: static fn(array $pair): CompositeSpecification => CompositeSpecification::create()
                    ->withSpecification($pair[0])
                    ->withSpecification($pair[1]),
            )],
            [2, Gen::map(
                inner: Gen::tuple($inner, $inner),
                map: static fn(array $pair): OrSpecification => OrSpecification::create($pair[0], $pair[1]),
            )],
        ]);
    }

    /**
     * @return list<string> Every `:name` token in the string conditions of a WHERE tree.
     */
    private static function referencedPlaceholders(string|array|ExpressionInterface|null $where): array
    {
        if ($where === null || $where instanceof ExpressionInterface) {
            return [];
        }
        if (is_string($where)) {
            preg_match_all('/(?<![:\\w]):\\w+/', $where, $matches);

            return $matches[0];
        }

        $placeholders = [];
        foreach ($where as $part) {
            if (is_string($part) || is_array($part) || $part instanceof ExpressionInterface) {
                $placeholders = [...$placeholders, ...self::referencedPlaceholders($part)];
            }
        }

        return $placeholders;
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

    /**
     * @return iterable<array{0: Specification, 1: Specification}>
     */
    public static function andIsCommutativeExamples(): iterable
    {
        $c = self::operandCatalog();

        yield 'identity (same leaf twice)' => [$c['active'], $c['active']];
        yield 'NOT-wrapped left' => [$c['notActive'], $c['inactive']];
        yield 'NOT-wrapped right' => [$c['inactive'], $c['notActive']];
        yield 'double negation' => [$c['doubleNeg'], $c['pricey']];
        yield 'disjoint (empty intersection)' => [$c['active'], $c['inactive']];
        yield 'universal vs empty' => [$c['all'], $c['none']];
        yield 'raw leaves sharing :price (#31)' => [$c['rawAbove'], $c['rawBelow']];
        yield 'raw :price next to raw :price/:price_max (#30)' => [$c['rawAbove'], $c['rawRange']];
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

    /**
     * @return iterable<array{0: Specification, 1: Specification, 2: Specification}>
     */
    public static function andIsAssociativeExamples(): iterable
    {
        $c = self::operandCatalog();

        yield 'all-empty parentheses collapse' => [$c['none'], $c['none'], $c['none']];
        yield 'NOT-wrapped middle operand' => [$c['active'], $c['notActive'], $c['pricey']];
        yield 'double negation as one operand' => [$c['doubleNeg'], $c['active'], $c['inactive']];
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

    /**
     * @return iterable<array{0: Specification, 1: Specification}>
     */
    public static function orIsCommutativeExamples(): iterable
    {
        $c = self::operandCatalog();

        yield 'identity (same leaf twice)' => [$c['active'], $c['active']];
        yield 'NOT-wrapped left' => [$c['notActive'], $c['inactive']];
        yield 'disjoint union is full coverage' => [$c['active'], $c['inactive']];
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

    /**
     * @return iterable<array{0: Specification, 1: Specification, 2: Specification}>
     */
    public static function orIsAssociativeExamples(): iterable
    {
        $c = self::operandCatalog();

        yield 'all-empty union stays empty' => [$c['none'], $c['none'], $c['none']];
        yield 'NOT-wrapped middle operand' => [$c['active'], $c['notActive'], $c['pricey']];
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

    /**
     * @return iterable<array{0: Specification}>
     */
    public static function andIsIdempotentExamples(): iterable
    {
        $c = self::operandCatalog();

        yield 'empty result' => [$c['none']];
        yield 'NOT-wrapped' => [$c['notActive']];
        yield 'double negation' => [$c['doubleNeg']];
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

    /**
     * Both sides of the law use the same NOT-handling path; mutation proof
     * (issue #22) showed that an early return in {@see QueryBuildingVisitor::visitNot()}
     * passes random search when both sides use the broken NOT identically,
     * but a hand-written edge case catches it. These examples pin that.
     *
     * @return iterable<array{0: Specification, 1: Specification}>
     */
    public static function deMorganAndExamples(): iterable
    {
        $c = self::operandCatalog();

        yield 'both empty (truth-table extreme)' => [$c['none'], $c['none']];
        yield 'both universal' => [$c['all'], $c['all']];
        yield 'NOT-wrapped both sides' => [$c['notActive'], $c['notActive']];
        yield 'double negation left' => [$c['doubleNeg'], $c['inactive']];
        yield 'raw leaves sharing :price inside one NOT (#31)' => [$c['rawAbove'], $c['rawBelow']];
        yield 'raw :price/:price_max under NOT next to raw :price (#30)' => [$c['rawRange'], $c['rawAbove']];
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

    /**
     * @return iterable<array{0: Specification, 1: Specification}>
     */
    public static function deMorganOrExamples(): iterable
    {
        $c = self::operandCatalog();

        yield 'both empty (truth-table extreme)' => [$c['none'], $c['none']];
        yield 'both universal' => [$c['all'], $c['all']];
        yield 'NOT-wrapped both sides' => [$c['notActive'], $c['notActive']];
        yield 'double negation left' => [$c['doubleNeg'], $c['inactive']];
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

    /**
     * @return iterable<array{0: Specification, 1: Specification, 2: Specification}>
     */
    public static function distributiveExamples(): iterable
    {
        $c = self::operandCatalog();

        yield 'a empty (left factor empty)' => [$c['none'], $c['active'], $c['inactive']];
        yield 'b == c (a AND (b OR b) == a AND b)' => [$c['active'], $c['pricey'], $c['pricey']];
        yield 'NOT-wrapped a' => [$c['notActive'], $c['active'], $c['inactive']];
    }

    /**
     * The invariant that found #30 downstream: whatever the tree, the set of
     * placeholders the WHERE references is exactly the set of keys the query
     * binds — a rename that misses a token, or a value overwritten by a
     * sibling, shows up here as a dangling or an unreferenced name.
     */
    #[Property(runs: 300)]
    public function everyReferencedPlaceholderIsBoundExactlyOnce(Specification $tree): void
    {
        $query = (new Query($this->db))->select('id')->from('items');
        QueryApplier::apply(specification: $tree, query: $query);

        $referenced = self::referencedPlaceholders($query->getWhere());
        $bound = array_map(strval(...), array_keys($query->getParams()));
        sort($referenced);
        sort($bound);

        Classify::cover(condition: $bound === [], label: 'no named placeholders', minPercent: 5);
        Classify::cover(condition: in_array(needle: ':price_0', haystack: $bound, strict: true), label: 'collision renamed', minPercent: 15);
        Classify::cover(condition: in_array(needle: ':price_max', haystack: $bound, strict: true), label: 'prefixed sibling present', minPercent: 15);

        Assert::same(array_values(array_unique($referenced)), $bound);
        $query->all();
    }

    /** @return array<string, ArbitraryInterface> */
    public static function everyReferencedPlaceholderIsBoundExactlyOnceGenerators(): array
    {
        return ['tree' => self::tree()];
    }

    /**
     * @return iterable<array{0: Specification}>
     */
    public static function everyReferencedPlaceholderIsBoundExactlyOnceExamples(): iterable
    {
        $c = self::operandCatalog();

        yield 'issue #30 repro' => [
            CompositeSpecification::create()->withSpecification($c['rawAbove'])->withNot($c['rawRange']),
        ];
        yield 'issue #31 repro' => [
            CompositeSpecification::create()->withSpecification($c['rawAbove'])->withSpecification($c['rawBelow']),
        ];
        yield 'twice-colliding leaf renamed in one pass' => [
            CompositeSpecification::create()
                ->withSpecification($c['rawAbove'])
                ->withNot(CompositeSpecification::create()->withSpecification($c['rawAbove'])->withSpecification($c['rawBelow'])),
        ];
        yield 'no raw leaves at all' => [
            CompositeSpecification::create()->withSpecification($c['active'])->withNot($c['pricey']),
        ];
    }
}
