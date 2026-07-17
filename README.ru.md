# Расуваефф/спецификация
[![Latest Stable Version](https://poser.pugx.org/rasuvaeff/specification/v)](https://packagist.org/packages/rasuvaeff/specification)
[![Total Downloads](https://poser.pugx.org/rasuvaeff/specification/downloads)](https://packagist.org/packages/rasuvaeff/specification)
[![Build](https://github.com/rasuvaeff/specification/actions/workflows/build.yml/badge.svg)](https://github.com/rasuvaeff/specification/actions/workflows/build.yml)
[![Static analysis](https://github.com/rasuvaeff/specification/actions/workflows/static-analysis.yml/badge.svg)](https://github.com/rasuvaeff/specification/actions/workflows/static-analysis.yml)
[![Psalm level](https://img.shields.io/badge/psalm-level_1-blue.svg)](https://github.com/rasuvaeff/specification/actions/workflows/static-analysis.yml)
[![PHP](https://img.shields.io/packagist/dependency-v/rasuvaeff/specification/php)](https://packagist.org/packages/rasuvaeff/specification)
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
> **Используете помощника по кодированию с помощью искусственного интеллекта?** [`llms.txt`](llms.txt) — это компактный
 > автономный справочник по всему общедоступному API плюс рецепты копирования и вставки —
 > поместите его в контекст модели. Авторы: см. [`AGENTS.md`](AGENTS.md). @@ЛИНИЯ@@
## Требования
- PHP 8.3+
 - `yiisoft/db` ^2.0.1

## Установка
```
composer require rasuvaeff/specification
```
## Использование
### Разработчик спецификаций
Свободный конструктор для составления условий запроса:

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
 | `где($col, $val, $op)` | `col op val` (любой оператор) |
 | `whereEqual($col, $val)` | `col = значение` |
 | `whereNotEqual($col, $val)` | `col != значение` |
 | `whereGreaterThan($col, $val)` | `col > val` |
 | `whereGreaterThanOrEqual($col, $val)` | `col >= val` |
 | `whereLessThan($col, $val)` | `col <значение` |
 | `whereLessThanOrEqual($col, $val)` | `col <= значение` |
 | `whereIn($col, $values)` | `col IN (значения)` |
 | `whereNotIn($col, $values)` | `столбец НЕ ВХОДИТ (значения)` |
 | `whereLike($col, $pattern)` | `шаблон col LIKE` |
 | `whereNotLike($col, $pattern)` | `col NOT LIKE шаблон` |
 | `whereBetween($col, $from, $to)` | `col BETWEEN from AND to` |
 | `whereNotBetween($col, $from, $to)` | `col NOT BETWEEN from AND to` |
 | `whereIlike($col, $pattern)` | `шаблон col ILIKE` |
 | `whereNotIlike($col, $pattern)` | `цвет НЕ НРАВИТСЯ шаблон` |
 | `whereStartsWith($col, $prefix)` | `col LIKE префикс%` |
 | `whereEndsWith($col, $suffix)` | `col LIKE %suffix` |
 | `whereContains($col, $substring)` | `col LIKE %substring%` |
 | `whereNull($col)` | `col IS NULL` |
 | `whereNotNull($col)` | `col НЕ NULL` |
 | `илиГде(вызываемый)` | `ИЛИ (вложенные условия)` |
 | `notWhere(вызываемый)` | `НЕ (вложенные условия)` |
 | `orderBy($columns)` | `ORDER BY col [ASC\|DESC]` |
 | `предел($n)` | `ОГРАНИЧЕНИЕ n` |
 | `смещение($n)` | `СМЕЩ n` | @@ЛИНИЯ@@
### Технические характеристики
Строительные блоки для составления сложных условий:

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
### Фабричные методы сравнения и спецификации
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
### Пользовательский посетитель
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
Запускаемые автономные примеры (SQLite в памяти) находятся в [`examples/`](examples/):
 `builder.php` (AND/IN/BETWEEN) и `or-not-raw.php` (OR/NOT/raw/order+limit). @@ЛИНИЯ@@
```bash
composer install && php examples/builder.php
```
## Безопасность
- **Значения параметризованы.** Все значения сравнения/IN/BETWEEN/LIKE привязаны
 как параметры к `yiisoft/db`, поэтому они защищены от SQL-инъекций.
 - **Имена столбцов не проверяются** — они передаются в `yiisoft/db` и заключаются в кавычки
 в качестве идентификаторов, но список разрешений отсутствует. Передавайте только **доверенные** имена столбцов
 (обычно жестко закодированные), но никогда не вводимые пользователем данные.
 — **`RawSpecification` — это необработанный выходной штрих.** Строка условия **не**
 экранирована — никогда не создавайте ее на основе ненадежных входных данных. Передавайте пользовательские значения только через
 карту `$params` (заполнители): `new RawSpecification('age > :age', ['age' => $value])`. @@ЛИНИЯ@@
## Производительность
`SpecificationBuilder` является неизменяемым — каждый вызов `where*()`, `limit()` и `offset()`
 клонирует построитель перед возвратом. Это безопасно и предсказуемо, но требует небольших накладных расходов
 (~3,3 мкс для 7-шаговой цепочки против ~2,4 мкс для прямой композиции `CompositeSpecification`
). `orWhere()` дополнительно выделяет временный построитель и вызывает замыкание
 (~2,8 мкс против ~1,4 мкс для прямого `OrSpecification::create()`).

 Для большинства рабочих нагрузок веб-запросов (1–5 спецификаций на запрос, запросы к базе данных занимают 1–100 мс)
 эти издержки незначительны. Для **высокопроизводительной пакетной обработки**, когда спецификации
 построены в узком цикле, отдайте предпочтение прямому API `CompositeSpecification`:

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
Тесты находятся в `benchmarks/` и запускаются через `composer Bench` (требуется
[testo/bench](https://github.com/php-testo/testo)).
## Примечания
- `ilike`/`not ilike` специфичны для PostgreSQL; другие драйверы (например, MySQL)
 их не поддерживают. Используйте `like` для необходимости учета регистра в этих драйверах.
 — для условий ИЛИ используйте `OrSpecification` или `SpecificationBuilder::orWhere()`.
 `CompositeSpecification` компонуется с семантикой **И**.
 - форматы значений `withOrCondition()`: скаляр представляет собой простое равенство (`'status' => 'active'`);
 массив, первым элементом которого является известный оператор, является сокращением
 (`'age' => ['>', 18]`, `'type' => ['in', ['a', 'b']]`); любой другой массив рассматривается как значение
, поэтому простой список (`'name' => ['a', 'b']`) становится условием `'IN`. Оператор
 сопоставляется без учета регистра. @@ЛИНИЯ@@
## Лицензия
BSD-3-пункт.
