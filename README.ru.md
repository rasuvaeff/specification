# rasuvaeff/specification

[![Latest Stable Version](https://poser.pugx.org/rasuvaeff/specification/v)](https://packagist.org/packages/rasuvaeff/specification)
[![Total Downloads](https://poser.pugx.org/rasuvaeff/specification/downloads)](https://packagist.org/packages/rasuvaeff/specification)
[![Build](https://github.com/rasuvaeff/specification/actions/workflows/build.yml/badge.svg)](https://github.com/rasuvaeff/specification/actions/workflows/build.yml)
[![Static analysis](https://github.com/rasuvaeff/specification/actions/workflows/static-analysis.yml/badge.svg)](https://github.com/rasuvaeff/specification/actions/workflows/static-analysis.yml)
[![Psalm level](https://img.shields.io/badge/psalm-level_1-blue.svg)](https://github.com/rasuvaeff/specification/actions/workflows/static-analysis.yml)
[![PHP](https://img.shields.io/packagist/dependency-v/rasuvaeff/specification/php)](https://packagist.org/packages/rasuvaeff/specification)
[![License](https://img.shields.io/badge/license-BSD--3--Clause-blue.svg)](LICENSE.md)
[English version](README.md)

Паттерн Specification для построения запросов к [Yiisoft DB](https://github.com/yiisoft/db).

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

> **Используете AI-ассистента?** [`llms.txt`](llms.txt) — компактный
> самодостаточный справочник по всему публичному API плюс готовые рецепты —
> добавьте его в контекст модели. Контрибьюторам: см. [`AGENTS.md`](AGENTS.md).

## Требования

- PHP 8.3+
- `yiisoft/db` ^2.0.1

## Установка

```
composer require rasuvaeff/specification
```

## Использование

### SpecificationBuilder

Fluent-билдер для композиции условий запроса:

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

Доступные методы:

| Метод | SQL-эквивалент |
|--------|---------------|
| `where($col, $val, $op)` | `col op val` (любой оператор) |
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

Строительные блоки для композиции сложных условий:

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

### Фабричные методы ComparisonSpecification

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

### Собственный visitor

Реализуйте `SpecificationVisitor<T>` для обхода дерева спецификаций:

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

## Примеры

Запускаемые офлайн-примеры (in-memory SQLite) лежат в [`examples/`](examples/):
`builder.php` (AND/IN/BETWEEN) и `or-not-raw.php` (OR/NOT/raw/order+limit).

```bash
composer install && php examples/builder.php
```

## Безопасность

- **Значения параметризованы.** Все значения в сравнениях/IN/BETWEEN/LIKE
  биндятся `yiisoft/db` как параметры, поэтому защищены от SQL-инъекций.
- **Имена колонок не валидируются** — они передаются в `yiisoft/db` и
  квотируются как идентификаторы, но allow-list'а нет. Передавайте только
  **доверенные** имена колонок (как правило, захардкоженные), никогда — сырой
  пользовательский ввод.
- **`RawSpecification` — это сырой escape hatch.** Строка условия **не**
  экранируется — никогда не собирайте её из недоверенного ввода. Пользовательские
  значения передавайте только через карту `$params` (плейсхолдеры):
  `new RawSpecification('age > :age', ['age' => $value])`.

## Производительность

`SpecificationBuilder` иммутабелен — каждый вызов `where*()`, `limit()` и
`offset()` клонирует builder перед возвратом. Это безопасно и предсказуемо, но
даёт небольшой оверхед (~3,3 мкс для 7-шаговой цепочки против ~2,4 мкс при прямой
композиции через `CompositeSpecification`). `orWhere()` дополнительно выделяет
временный builder и вызывает замыкание (~2,8 мкс против ~1,4 мкс у прямого
`OrSpecification::create()`).

Для большинства веб-нагрузок (1–5 спецификаций на запрос, запросы к БД занимают
1–100 мс) этот оверхед пренебрежимо мал. Для **высоконагруженной пакетной
обработки**, где спецификации строятся в плотном цикле, предпочитайте прямой API
`CompositeSpecification`:

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

Бенчмарки лежат в `benchmarks/` и запускаются через `composer bench` (требуется
[testo/bench](https://github.com/php-testo/testo)).

## Замечания

- `ilike`/`not ilike` специфичны для PostgreSQL; другие драйверы (например,
  MySQL) их не поддерживают. Для case-insensitive-нужд на таких драйверах
  используйте `like`.
- Для OR-условий используйте `OrSpecification` либо `SpecificationBuilder::orWhere()`.
  `CompositeSpecification` компонует с **AND**-семантикой.
- Форматы значений `withOrCondition()`: скаляр — это plain equality
  (`'status' => 'active'`); массив, первый элемент которого — известный оператор,
  — сокращение (`'age' => ['>', 18]`, `'type' => ['in', ['a', 'b']]`); любой
  другой массив трактуется как значение, поэтому простой список
  (`'name' => ['a', 'b']`) становится условием `IN`. Оператор сопоставляется
  case-insensitively.

## Лицензия

BSD-3-Clause.
