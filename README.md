# rasuvaeff/specification

[![Latest Stable Version](https://poser.pugx.org/rasuvaeff/specification/v)](https://packagist.org/packages/rasuvaeff/specification)
[![Total Downloads](https://poser.pugx.org/rasuvaeff/specification/downloads)](https://packagist.org/packages/rasuvaeff/specification)
[![Build](https://github.com/rasuvaeff/specification/actions/workflows/build.yml/badge.svg)](https://github.com/rasuvaeff/specification/actions/workflows/build.yml)
[![Static analysis](https://github.com/rasuvaeff/specification/actions/workflows/static-analysis.yml/badge.svg)](https://github.com/rasuvaeff/specification/actions/workflows/static-analysis.yml)
[![Coverage](https://codecov.io/gh/rasuvaeff/specification/branch/master/graph/badge.svg)](https://codecov.io/gh/rasuvaeff/specification)
[![Psalm level](https://img.shields.io/badge/psalm-level_1-blue.svg)](https://github.com/rasuvaeff/specification/actions/workflows/static-analysis.yml)
[![License](https://img.shields.io/badge/license-BSD--3--Clause-blue.svg)](LICENSE.md)

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
| `whereIlike($col, $pattern)` | `col ILIKE pattern` |
| `whereNotIlike($col, $pattern)` | `col NOT ILIKE pattern` |
| `whereStartsWith($col, $prefix)` | `col LIKE prefix%` |
| `whereEndsWith($col, $suffix)` | `col LIKE %suffix` |
| `whereContains($col, $substring)` | `col LIKE %substring%` |
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
- **Column names are not validated** — they are passed to `yiisoft/db` and quoted
  as identifiers, but there is no allow-list. Pass only **trusted** column names
  (typically hard-coded), never raw user input.
- **`RawSpecification` is a raw escape hatch.** The condition string is **not**
  escaped — never build it from untrusted input. Pass user values only through
  the `$params` map (placeholders): `new RawSpecification('age > :age', ['age' => $value])`.

## Notes

- `ilike` / `not ilike` are PostgreSQL-specific; other drivers (e.g. MySQL) do not
  support them. Use `like` for case-insensitive needs on those drivers.
- For OR conditions use `OrSpecification` or `SpecificationBuilder::orWhere()`.
  `CompositeSpecification` composes with **AND** semantics.
- `withOrCondition()` value formats: a scalar is plain equality (`'status' => 'active'`);
  an array whose first element is a known operator is a shorthand
  (`'age' => ['>', 18]`, `'type' => ['in', ['a', 'b']]`); any other array is treated as
  a value, so a plain list (`'name' => ['a', 'b']`) becomes an `IN` condition. The
  operator is matched case-insensitively.

## License

BSD-3-Clause.
