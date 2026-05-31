<?php

declare(strict_types=1);

use Rasuvaeff\Specification\CompositeSpecification;
use Rasuvaeff\Specification\OrSpecification;
use Rasuvaeff\Specification\QueryApplier;
use Rasuvaeff\Specification\RawSpecification;
use Rasuvaeff\Specification\SpecificationBuilder;
use Yiisoft\Db\Query\Query;

require __DIR__ . '/_bootstrap.php';

$db = example_db();

// OR: (status = 'inactive') OR (price = 20). Each branch binds its own
// parameter — placeholders never collide.
$orSpec = CompositeSpecification::create()->withSpecification(
    OrSpecification::create(
        SpecificationBuilder::create()->whereEqual('status', 'inactive')->build(),
        SpecificationBuilder::create()->whereEqual('price', 20)->build(),
    ),
);
$orQuery = (new Query($db))->from('items');
QueryApplier::apply($orSpec, $orQuery);
echo 'SQL: ' . $orQuery->createCommand()->getRawSql() . "\n";
print_rows('status = inactive OR price = 20:', $orQuery->all());

// NOT: NOT (status = 'active').
$notSpec = SpecificationBuilder::create()->notWhere(
    static fn (SpecificationBuilder $b): SpecificationBuilder => $b->whereEqual('status', 'active'),
)->build();
$notQuery = (new Query($db))->from('items');
QueryApplier::apply($notSpec, $notQuery);
print_rows('NOT (status = active):', $notQuery->all());

// RawSpecification — value bound via :params (never interpolate user input).
// Use withRaw() shorthand on CompositeSpecification.
$rawSpec = CompositeSpecification::create()
    ->withRaw('price > :min', ['min' => 30]);
$rawQuery = (new Query($db))->from('items');
QueryApplier::apply($rawSpec, $rawQuery);
print_rows('raw: price > :min (30):', $rawQuery->all());

// Order by + limit + offset via SpecificationBuilder.
$pageSpec = SpecificationBuilder::create()
    ->whereNotNull('created_at')
    ->orderBy(['created_at' => 'DESC'])
    ->limit(2)
    ->offset(1)
    ->build();
$pageQuery = (new Query($db))->from('items');
QueryApplier::apply($pageSpec, $pageQuery);
print_rows('ORDER BY created_at DESC LIMIT 2 OFFSET 1:', $pageQuery->all());
