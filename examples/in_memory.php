<?php

declare(strict_types=1);

use Rasuvaeff\Specification\ComparisonSpecification;
use Rasuvaeff\Specification\CompositeSpecification;
use Rasuvaeff\Specification\InMemorySpecification;
use Rasuvaeff\Specification\NotSpecification;
use Rasuvaeff\Specification\OrSpecification;
use Rasuvaeff\Specification\QueryApplier;
use Yiisoft\Db\Query\Query;

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once __DIR__ . '/_bootstrap.php';

$db = example_db();

// One rule, written once: the away-end rule shape — an active seat that is
// neither held nor blocked.
$rule = CompositeSpecification::create()
    ->withComparison('status', 'active')
    ->withComparison('price', 25, '>');

// The SQL side: the read model lists.
$query = (new Query($db))->select('id')->from('items');
QueryApplier::apply(specification: $rule, query: $query);
// PDO SQLite returns the ids as strings; both sides go through (int).
$selected = array_map(intval(...), $query->column());

// The in-memory side: the aggregate refuses one row it already holds.
/** @var array<string, mixed> $row */
foreach ((new Query($db))->from('items')->all() as $row) {
    $row['id'] = (int) $row['id'];
    printf(
        "row %d: in-memory %s, selected by SQL %s\n",
        $row['id'],
        InMemorySpecification::satisfies(specification: $rule, row: $row) ? 'true ' : 'false',
        in_array(needle: $row['id'], haystack: $selected, strict: true) ? 'true ' : 'false',
    );
}

// NOT and NULL follow SQL's three-valued logic: NOT (price < 0) keeps no row
// with a NULL price — unknown stays unknown under negation.
$negated = new NotSpecification(ComparisonSpecification::lessThan(column: 'price', value: 0));
$either = OrSpecification::create(
    ComparisonSpecification::isNull(column: 'status'),
    ComparisonSpecification::in(column: 'name', values: ['alpha', 'bravo']),
);

printf("NOT (price < 0) on a null price: %s\n", InMemorySpecification::satisfies(specification: $negated, row: ['price' => null]) ? 'true' : 'false');
printf("status IS NULL OR name IN ('alpha', 'bravo'): %s\n", InMemorySpecification::satisfies(specification: $either, row: ['status' => null, 'name' => 'zulu']) ? 'true' : 'false');
