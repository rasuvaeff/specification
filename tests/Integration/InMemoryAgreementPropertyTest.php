<?php

declare(strict_types=1);

namespace Rasuvaeff\Specification\Tests\Integration;

use Rasuvaeff\PropertyTesting\ArbitraryInterface;
use Rasuvaeff\PropertyTesting\Gen;
use Rasuvaeff\PropertyTesting\Property;
use Rasuvaeff\Specification\ComparisonSpecification;
use Rasuvaeff\Specification\CompositeSpecification;
use Rasuvaeff\Specification\InMemorySpecification;
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
 * The guard of #40: one rule, two interpreters, one answer. A specification
 * the SQL side renders through {@see QueryApplier} must select exactly the
 * rows {@see InMemorySpecification} answers `true` for — per row, on a fixed
 * in-memory SQLite table whose columns carry the shapes the interpreter
 * pinned: integers, non-numeric strings (byte-wise both sides), canonical
 * numeric strings in a TEXT column (equality class only), and NULLs.
 *
 * The generator stays inside the unambiguous domain on purpose: a
 * non-numeric string against a number is refused by the interpreter (the
 * engines disagree on that coercion) and is pinned as a refusal in the unit
 * suite, not as agreement here.
 */
#[Test]
#[CoversNothing]
final class InMemoryAgreementPropertyTest
{
    private const array NAMES = ['alpha', 'bravo', 'charlie', 'delta', 'echo', 'foxtrot', 'golf', 'hotel'];

    private Connection $db;

    /** @var array<int, array<string, mixed>> */
    private array $rows;

    #[BeforeTest]
    public function setUp(): void
    {
        $this->db = new Connection(new Driver('sqlite::memory:'), new SchemaCache(new ArrayCache()));

        $this->db->createCommand(
            'CREATE TABLE items (id INTEGER PRIMARY KEY, name TEXT, status TEXT, price INTEGER, code TEXT)',
        )->execute();

        // NULLs in every nullable column, numeric strings in `code`, and the
        // NULL-row shapes the SQL side excludes from every predicate but
        // IS NULL / IS NOT NULL.
        $rows = [
            [1, 'alpha', 'active', 10, '10'],
            [2, 'bravo', 'active', 20, '20'],
            [3, 'charlie', null, 30, '30'],
            [4, 'delta', 'inactive', 40, '40'],
            [5, 'echo', 'inactive', 50, '50'],
            [6, null, 'active', 0, '0'],
            [7, 'golf', 'active', 60, '60'],
            [8, 'hotel', 'banned', 25, null],
            [9, 'india', null, null, '15'],
            [10, 'juliet', 'active', 5, null],
            [11, null, null, null, null],
            [12, 'kilo', 'new', 35, '35'],
        ];
        $this->db->createCommand()
            ->insertBatch('items', $rows, ['id', 'name', 'status', 'price', 'code'])
            ->execute();

        $this->rows = (new Query($this->db))->from('items')->all();
    }

    #[Property(runs: 200)]
    public function interpretersAgreeOnEveryRow(Specification $specification): void
    {
        $selected = $this->selectedIds($specification);

        foreach ($this->rows as $row) {
            // PDO SQLite hands INTEGER PRIMARY KEY columns back as strings;
            // both sides go through (int) so the strict comparison is honest.
            $id = (int) $row['id'];
            Assert::same(
                InMemorySpecification::satisfies(specification: $specification, row: $row),
                in_array(needle: $id, haystack: $selected, strict: true),
                sprintf('Row %d disagrees with SQL for the same specification', $id),
            );
        }
    }

    /** @return array<string, ArbitraryInterface> */
    public static function interpretersAgreeOnEveryRowGenerators(): array
    {
        return ['specification' => self::specification()];
    }

    /**
     * @return iterable<string, array{Specification}>
     */
    public static function interpretersAgreeOnEveryRowExamples(): iterable
    {
        // The #40 pin itself: a '1' column against 1 — a numeric-string TEXT
        // column and an integer literal agree in SQL (both engines) and in
        // memory after the interpreter's coercion.
        yield 'the issue pin: a numeric-string column against an integer literal' => [
            ComparisonSpecification::equal(column: 'code', value: 20),
        ];

        // The same class from the literal side: an integer column against a
        // numeric-string literal, equality only.
        yield 'numeric-string literal against an integer column' => [
            ComparisonSpecification::equal(column: 'price', value: '10'),
        ];

        // A canonical numeric string in a TEXT column, equality class.
        yield 'text column with a numeric string' => [
            ComparisonSpecification::equal(column: 'code', value: '20'),
        ];

        // NULL rows are excluded from every predicate…
        yield 'null rows never selected by a comparison' => [
            ComparisonSpecification::greaterThan(column: 'price', value: 10),
        ];

        // …but named by IS NULL / IS NOT NULL.
        yield 'is null names the null rows' => [
            ComparisonSpecification::isNull(column: 'status'),
        ];

        // One NULL entry poisons NOT IN entirely — on both sides.
        yield 'not in poisoned by a null entry' => [
            ComparisonSpecification::notIn(column: 'status', values: [null, 'banned']),
        ];

        // An empty NOT IN admits everything, as SQL's vacuous reading has it.
        yield 'empty not in admits everything' => [
            ComparisonSpecification::notIn(column: 'status', values: []),
        ];

        // Double negation collapses on both sides.
        yield 'double negation collapses' => [
            new NotSpecification(new NotSpecification(ComparisonSpecification::equal(column: 'status', value: 'active'))),
        ];
    }

    /** @return list<int> */
    private function selectedIds(Specification $specification): array
    {
        $query = (new Query($this->db))->select('id')->from('items');
        QueryApplier::apply(specification: $specification, query: $query);

        $ids = array_map(static fn(array|object $row): int => (int) ((array) $row)['id'], $query->all());
        sort($ids);

        return $ids;
    }

    /**
     * The unambiguous domain: comparisons over the integer column (with the
     * numeric-string coercion class), byte-wise string columns, the equality
     * class of the numeric-string TEXT column, NULL checks, and the list and
     * range shapes — composed by AND, OR and NOT.
     */
    private static function specification(): ArbitraryInterface
    {
        return Gen::frequency([
            [5, self::leaf()],
            [2, self::composite()],
            [2, self::or()],
            [1, self::negation()],
        ]);
    }

    private static function leaf(): ArbitraryInterface
    {
        return Gen::frequency([
            [4, self::priceComparison()],
            [3, self::stringComparison(column: 'name')],
            [2, self::stringComparison(column: 'status')],
            [2, self::codeEquality()],
            [1, self::nullCheck()],
            [2, self::priceList()],
            [1, self::priceBetween()],
        ]);
    }

    private static function priceComparison(): ArbitraryInterface
    {
        // Ordering operators take int literals only: SQLite does not apply
        // numeric affinity to a text operand of `<`/`>` (all integers sort
        // before all text) while MySQL coerces — the engines disagree, so the
        // shape is outside the agreement domain (the interpreter still
        // coerces it in memory; the unit suite pins that reading). The
        // equality class admits numeric-string literals: there both engines
        // and the interpreter agree.
        return Gen::frequency([
            [3, Gen::map(
                inner: Gen::tuple(
                    Gen::elements(values: ['<', '<=', '>', '>=']),
                    Gen::intBetween(min: 0, max: 60),
                ),
                map: static fn(array $pair): ComparisonSpecification => new ComparisonSpecification(
                    column: 'price',
                    value: $pair[1],
                    operator: $pair[0],
                ),
            )],
            [2, Gen::map(
                inner: Gen::tuple(
                    Gen::elements(values: ['=', '!=', '<>']),
                    Gen::frequency([
                        [7, Gen::intBetween(min: 0, max: 60)],
                        [3, Gen::elements(values: ['15', '30', '45'])],
                    ]),
                ),
                map: static fn(array $pair): ComparisonSpecification => new ComparisonSpecification(
                    column: 'price',
                    value: $pair[1],
                    operator: $pair[0],
                ),
            )],
        ]);
    }

    private static function stringComparison(string $column): ArbitraryInterface
    {
        return Gen::map(
            inner: Gen::tuple(
                Gen::elements(values: ['=', '!=', '<>', '<', '<=', '>', '>=']),
                Gen::elements(values: self::NAMES),
            ),
            map: static fn(array $pair): ComparisonSpecification => new ComparisonSpecification(
                column: $column,
                value: $pair[1],
                operator: $pair[0],
            ),
        );
    }

    /**
     * `code` is TEXT with canonical numeric strings: the equality class
     * agrees on both sides ('20' = '20' byte-wise in SQL, numerically after
     * the interpreter's coercion); ordering there is the ambiguous class the
     * interpreter refuses, so it is not generated.
     */
    private static function codeEquality(): ArbitraryInterface
    {
        $values = ['10', '20', '30', '40', '50', '15', '35', '0', '60'];

        return Gen::frequency([
            [2, Gen::map(
                inner: Gen::elements(values: ['=', '!=']),
                map: static fn(string $operator): ComparisonSpecification => new ComparisonSpecification(
                    column: 'code',
                    value: $values[random_int(0, count($values) - 1)],
                    operator: $operator,
                ),
            )],
            [1, Gen::map(
                inner: Gen::elements(values: ['in', 'not in']),
                map: static fn(string $operator): ComparisonSpecification => new ComparisonSpecification(
                    column: 'code',
                    value: array_slice($values, 0, random_int(1, 3)),
                    operator: $operator,
                ),
            )],
        ]);
    }

    private static function nullCheck(): ArbitraryInterface
    {
        return Gen::map(
            inner: Gen::tuple(
                Gen::elements(values: ['name', 'status', 'price', 'code']),
                Gen::elements(values: ['is', 'is not']),
            ),
            map: static fn(array $pair): ComparisonSpecification => new ComparisonSpecification(
                column: $pair[0],
                value: null,
                operator: $pair[1],
            ),
        );
    }

    private static function priceList(): ArbitraryInterface
    {
        return Gen::map(
            inner: Gen::tuple(
                Gen::elements(values: ['in', 'not in']),
                Gen::frequency([
                    [4, Gen::nonEmptyArrayOf(Gen::intBetween(min: 0, max: 60), maxSize: 4)],
                    [1, Gen::map(
                        inner: Gen::nonEmptyArrayOf(Gen::intBetween(min: 0, max: 60), maxSize: 3),
                        map: static fn(array $values): array => [...$values, null],
                    )],
                ]),
            ),
            map: static fn(array $pair): ComparisonSpecification => new ComparisonSpecification(
                column: 'price',
                value: $pair[1],
                operator: $pair[0],
            ),
        );
    }

    private static function priceBetween(): ArbitraryInterface
    {
        return Gen::map(
            inner: Gen::tuple(
                Gen::elements(values: ['between', 'not between']),
                Gen::intBetween(min: 0, max: 60),
                Gen::intBetween(min: 0, max: 60),
            ),
            map: static fn(array $triple): ComparisonSpecification => new ComparisonSpecification(
                column: 'price',
                value: [$triple[1], $triple[2]],
                operator: $triple[0],
            ),
        );
    }

    private static function composite(): ArbitraryInterface
    {
        return Gen::map(
            inner: Gen::arrayOf(self::leaf(), minSize: 2, maxSize: 3),
            map: static fn(array $leaves): CompositeSpecification => new CompositeSpecification(specifications: $leaves),
        );
    }

    private static function or(): ArbitraryInterface
    {
        return Gen::map(
            inner: Gen::arrayOf(self::leaf(), minSize: 2, maxSize: 3),
            map: static fn(array $leaves): OrSpecification => OrSpecification::create(...$leaves),
        );
    }

    private static function negation(): ArbitraryInterface
    {
        return Gen::map(
            inner: self::leaf(),
            map: static fn(Specification $leaf): NotSpecification => new NotSpecification($leaf),
        );
    }
}
