<?php

declare(strict_types=1);

use Rasuvaeff\Specification\QueryApplier;
use Rasuvaeff\Specification\SpecificationBuilder;
use Yiisoft\Db\Query\Query;

require __DIR__ . '/_bootstrap.php';

$db = example_db();

// Fluent builder -> AND conditions, applied to a yiisoft/db Query.
$spec = SpecificationBuilder::create()
    ->whereEqual('status', 'active')
    ->whereGreaterThanOrEqual('price', 20)
    ->build();

$query = (new Query($db))->from('items');
QueryApplier::apply($spec, $query);

echo 'SQL: ' . $query->createCommand()->getRawSql() . "\n\n";
print_rows('active AND price >= 20:', $query->all());

// IN + BETWEEN.
$inQuery = (new Query($db))->from('items');
QueryApplier::apply(SpecificationBuilder::create()->whereIn('price', [10, 40])->build(), $inQuery);
print_rows('price IN (10, 40):', $inQuery->all());

$betweenQuery = (new Query($db))->from('items');
QueryApplier::apply(SpecificationBuilder::create()->whereBetween('price', 20, 40)->build(), $betweenQuery);
print_rows('price BETWEEN 20 AND 40:', $betweenQuery->all());
