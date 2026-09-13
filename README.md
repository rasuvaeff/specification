# rasuvaeff/specification

[![Latest Stable Version](https://poser.pugx.org/rasuvaeff/specification/v)](https://packagist.org/packages/rasuvaeff/specification)
[![Total Downloads](https://poser.pugx.org/rasuvaeff/specification/downloads)](https://packagist.org/packages/rasuvaeff/specification)
[![Build](https://github.com/rasuvaeff/specification/actions/workflows/build.yml/badge.svg)](https://github.com/rasuvaeff/specification/actions/workflows/build.yml)
[![Static analysis](https://github.com/rasuvaeff/specification/actions/workflows/static-analysis.yml/badge.svg)](https://github.com/rasuvaeff/specification/actions/workflows/static-analysis.yml)
[![Psalm level](https://img.shields.io/badge/psalm-level_1-blue.svg)](https://github.com/rasuvaeff/specification/actions/workflows/static-analysis.yml)
[![PHP](https://img.shields.io/packagist/dependency-v/rasuvaeff/specification/php)](https://packagist.org/packages/rasuvaeff/specification)
[![License](https://img.shields.io/badge/license-BSD--3--Clause-blue.svg)](LICENSE.md)
[Русская версия](README.ru.md)

Specification pattern for building [Yiisoft DB](https://github.com/yiisoft/db) queries.

```php
use Rasuvaeff\Specification\SpecificationBuilder;
use Rasuvaeff\Specification\QueryApplier;

$spec = SpecificationBuilder::create()
    ->whereEqual('status', 'active')
    ->whereGreaterThan('age', 18)
    ->whereIn('role', ['admin', 'moderator'])
    ->orderBy(['created_at' => 'DESC'])
    ->limit(20)
    ->build();

$query = (new \Yiisoft\Db\Query\Query($db))->from('users');
QueryApplier::apply($spec, $query);
$rows = $query->all();
```

> **Using an AI coding assistant?** [`llms.txt`](llms.txt) is a compact,
> self-contained reference of the whole public API plus copy-paste recipes —
> drop it into the model's context. Contributors: see [`AGENTS.md`](AGENTS.md).
> Projects using the [llm/skills](https://github.com/roxblnfk/skills) Composer
> plugin also get this package's agent skill synced into `.agents/skills/`
> automatically on install.

## Requirements

- PHP 8.3+
- `yiisoft/db` ^2.0.1

## Installation

```
composer require rasuvaeff/specification
```

## Usage

### SpecificationBuilder

Fluent builder for composing query conditions:

```php
use Rasuvaeff\Specification\SpecificationBuilder;
use Rasuvaeff\Specification\QueryApplier;

$spec = SpecificationBuilder::create()
    ->whereEqual('status', 'active')
    ->whereGreaterThan('age', 18)
    ->whereNull('deleted_at')
    ->build();

$query = (new Yiisoft\Db\Query\Query($db))->from('users');
QueryApplier::apply($spec, $query);
$rows = $query->all();
```

Available methods:

| Method | SQL equivalent |
|--------|---------------|
| `where($col, $val, $op)` | `col op val` (any operator) |
| `whereEqual($col, $val)` | `col = val` |
| `whereNotEqual($col, $val)` | `col != val` |
| `whereGreaterThan($col, $val)` | `col > val` |
| `whereGreaterThanOrEqual($col, $val)` | `col >= val` |
| `whereLessThan($col, $val)` | `col < val` |
| `whereLessThanOrEqual($col, $val)` | `col <= val` |
| `whereIn($col, $values)` | `col IN (values)` |
| `whereNotIn($col, $values)` | `col NOT IN (values)` |
| `whereLike($col, $pattern)` | `col LIKE pattern` |
| `whereNotLike($col, $pattern)` | `col NOT LIKE pattern` |
| `whereBetween($col, $from, $to)` | `col BETWEEN from AND to` |
| `whereNotBetween($col, $from, $to)` | `col NOT BETWEEN from AND to` |
| `whereIlike($col, $pattern)` | `col LIKE pattern`, case-insensitive |
| `whereNotIlike($col, $pattern)` | `col NOT LIKE pattern`, case-insensitive |
| `whereStartsWith($col, $prefix)` | `col LIKE prefix%` (prefix escaped) |
| `whereEndsWith($col, $suffix)` | `col LIKE %suffix` (suffix escaped) |
| `whereContains($col, $substring)` | `col LIKE %substring%` (substring escaped) |
| `whereNull($col)` | `col IS NULL` |
| `whereNotNull($col)` | `col IS NOT NULL` |
| `orWhere(callable)` | `OR (nested conditions)` |
| `notWhere(callable)` | `NOT (nested conditions)` |
| `orderBy($columns)` | `ORDER BY col [ASC\|DESC]` |
| `limit($n)` | `LIMIT n` |
| `offset($n)` | `OFFSET n` |

### Specifications

Building blocks for composing complex conditions:

```php
use Rasuvaeff\Specification\ComparisonSpecification;
use Rasuvaeff\Specification\CompositeSpecification;
use Rasuvaeff\Specification\NotSpecification;
use Rasuvaeff\Specification\OffsetSpecification;
use Rasuvaeff\Specification\OrConditionSpecification;
use Rasuvaeff\Specification\OrSpecification;
use Rasuvaeff\Specification\RawSpecification;

// AND conditions
$spec = CompositeSpecification::create()
    ->withComparison('status', 'active')
    ->withComparison('age', 18, '>')
    ->withOrderBy(['created_at' => 'DESC'])
    ->withLimit(20)
    ->withOffset(40);

// OR condition arrays via OrConditionSpecification.
$orConditionSpec = CompositeSpecification::create()
    ->withOrCondition(['status' => 'active', 'type' => 'pending']);

// OR conditions
$orSpec = OrSpecification::create(
    ComparisonSpecification::equal('type', 'admin'),
    ComparisonSpecification::equal('type', 'moderator'),
);

// NOT condition
$notSpec = new NotSpecification(
    new ComparisonSpecification('status', 'banned'),
);

// Raw SQL — see the Security note below
$rawSpec = new RawSpecification('age > :age', ['age' => 18]);

// Offset for pagination
$offset = CompositeSpecification::create()
    ->withLimit(10)
    ->withOffset(20);

// Raw SQL — see the Security note below
$rawComposite = CompositeSpecification::create()
    ->withRaw('price > :min AND price < :max', ['min' => 10, 'max' => 100]);
```

### ComparisonSpecification factory methods

```php
ComparisonSpecification::equal('col', $val)
ComparisonSpecification::notEqual('col', $val)
ComparisonSpecification::greaterThan('col', $val)
ComparisonSpecification::greaterThanOrEqual('col', $val)
ComparisonSpecification::lessThan('col', $val)
ComparisonSpecification::lessThanOrEqual('col', $val)
ComparisonSpecification::like('col', 'pattern')
ComparisonSpecification::notLike('col', 'pattern')
ComparisonSpecification::ilike('col', 'pattern')
ComparisonSpecification::notIlike('col', 'pattern')
ComparisonSpecification::startsWith('col', 'prefix')
ComparisonSpecification::endsWith('col', 'suffix')
ComparisonSpecification::contains('col', 'substring')
ComparisonSpecification::in('col', [1, 2, 3])
ComparisonSpecification::notIn('col', [4, 5, 6])
ComparisonSpecification::between('col', $from, $to)
ComparisonSpecification::notBetween('col', $from, $to)
ComparisonSpecification::isNull('col')
ComparisonSpecification::isNotNull('col')
```

### LIKE: patterns and substrings

`like()`, `notLike()`, `ilike()` and `notIlike()` take a **pattern**: it is
sent to the database verbatim, so `%` and `_` in it are wildcards and it is
the caller's job to escape a literal one. `startsWith()`, `endsWith()` and
`contains()` take a **plain string**: the query builder escapes `%`, `_` and
`\` in it and adds the wildcard itself, so `contains('name', '100%')` finds
the rows that contain `100%`.

`ilike()` / `notIlike()` are `LIKE` with `caseSensitive: false` in yiisoft/db
terms: PostgreSQL renders them as `ILIKE`, MySQL and SQLite as a plain `LIKE`
(both are case-insensitive by default for their usual collations).

The match mode travels with the specification as `LikeMatch` and is available
to custom visitors via `getLikeMatch()`. The constructor accepts it too:

```php
new ComparisonSpecification('name', 'abc', 'ilike', LikeMatch::Contains);
```

### Custom visitor

Implement `SpecificationVisitor<T>` to traverse the specification tree:

```php
use Rasuvaeff\Specification\SpecificationVisitor;
use Rasuvaeff\Specification\ComparisonSpecification;
// ... other specification imports

/** @implements SpecificationVisitor<int> */
final class CountingVisitor implements SpecificationVisitor
{
    private int $count = 0;

    #[\Override]
    public function visitComparison(ComparisonSpecification $specification): int
    {
        return ++$this->count;
    }

    // ... implement all visit* methods (visitComparison, visitComposite, visitNot,
    //     visitOr, visitOrCondition, visitRaw, visitOrderBy, visitLimit, visitOffset)
}
```

## Examples

Runnable, offline examples (in-memory SQLite) live in [`examples/`](examples/):
`builder.php` (AND/IN/BETWEEN) and `or-not-raw.php` (OR/NOT/raw/order+limit).

```bash
composer install && php examples/builder.php
```

## Security

- **Values are parameterized.** All comparison/IN/BETWEEN/LIKE values are bound
  as parameters by `yiisoft/db`, so they are safe against SQL injection.
- **OR condition operators are allow-listed.** Direct
  `OrConditionSpecification` conditions accept only the same canonical
  operators as `ComparisonSpecification`; an unknown operator throws
  `InvalidArgumentException` before query generation.
- **Column names are not validated** — they are passed to `yiisoft/db` and quoted
  as identifiers, but there is no allow-list. Pass only **trusted** column names
  (typically hard-coded), never raw user input.
- **`RawSpecification` is a raw escape hatch.** The condition string is **not**
  escaped — never build it from untrusted input. Pass user values only through
  the `$params` map (placeholders): `new RawSpecification('age > :age', ['age' => $value])`.

## Performance

`SpecificationBuilder` is immutable — each `where*()`, `limit()`, and `offset()` call
clones the builder before returning. This is safe and predictable but carries a small
overhead (~3.3µs for a 7-step chain, vs ~2.4µs for direct `CompositeSpecification`
composition). `orWhere()` additionally allocates a temporary builder and invokes a
closure (~2.8µs vs ~1.4µs for direct `OrSpecification::create()`).

For most web request workloads (1–5 specs per request, DB queries taking 1–100ms)
this overhead is negligible. For **high-throughput batch processing** where specs are
built in a tight loop, prefer the direct `CompositeSpecification` API:

```php
// ~26% faster than SpecificationBuilder for a 7-condition chain
$spec = CompositeSpecification::create()
    ->withComparison('status', 'active')
    ->withComparison('age', 18, '>')
    ->withComparison('role', ['admin', 'editor'], 'in')
    ->withLimit(100);

// ~48% faster than orWhere() for OR composition
$spec = CompositeSpecification::create()
    ->withSpecification(OrSpecification::create(
        CompositeSpecification::create()->withComparison('status', 'active'),
        CompositeSpecification::create()->withComparison('status', 'pending'),
    ));
```

Benchmarks live in `benchmarks/` and run via `composer bench` (requires
[testo/bench](https://github.com/php-testo/testo)).

## Notes

- `ilike` / `not ilike` are rendered as `ILIKE` on PostgreSQL and as a plain
  `LIKE` elsewhere; see "LIKE: patterns and substrings".
- For OR conditions use `OrSpecification` or `SpecificationBuilder::orWhere()`.
  `CompositeSpecification` composes with **AND** semantics.
- `RawSpecification` placeholders are isolated per leaf. A name that is
  already bound on the query — by an earlier raw leaf, a NOT/OR sub-query or
  the caller's own `where()` — is renamed to `:name_0`, `:name_1`, … in that
  leaf's condition (whole tokens only, so `:sort_max` survives a `:sort`
  rename), and `getParams()` keys are always colon-prefixed (`'age'` becomes
  `':age'`). Two raw leaves may therefore reuse `:price` under one AND and
  both values are bound. Positional `?` parameters are passed through
  untouched.
- `withOrCondition()` value formats: a scalar is plain equality (`'status' => 'active'`);
  an array whose first element is a known operator is a shorthand
  (`'age' => ['>', 18]`, `'type' => ['in', ['a', 'b']]`); any other array is treated as
  a value, so a plain list (`'name' => ['a', 'b']`) becomes an `IN` condition. The
  operator is matched case-insensitively. A `['like', pattern]` entry (and the
  other three LIKE operators) sends the pattern verbatim. `['is', value]` and
  `['is not', value]` are rendered as `=` / `!=`, exactly like the comparison
  path: a `null` operand becomes `IS NULL` / `IS NOT NULL`, anything else a plain
  equality — never a verbatim `IS 'value'`, which MySQL and PostgreSQL reject.
  A condition handed to `OrConditionSpecification` directly is normalized the
  same way only in its three-element `[operator, column, value]` form. Its
  list-form operator must be one of the allow-listed comparison operators.
- `orWhere()` and `notWhere()` callbacks may return the nested builder; doing so
  is required when the callback itself contains another `orWhere()` or
  `notWhere()`. `ORDER BY`, `LIMIT` and `OFFSET` are query-level modifiers and
  apply to the complete OR expression, even when written before or inside the
  callback.
- `DateTimeInterface` values are rendered through `yiisoft/db`'s
  timezone-aware `DateTimeValue` with six fractional digits. The target column
  should support the resulting timezone-aware datetime representation.

## License

BSD-3-Clause.
