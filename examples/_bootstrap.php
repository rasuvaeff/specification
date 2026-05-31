<?php

declare(strict_types=1);

use Yiisoft\Cache\ArrayCache;
use Yiisoft\Db\Cache\SchemaCache;
use Yiisoft\Db\Sqlite\Connection;
use Yiisoft\Db\Sqlite\Driver;

require_once dirname(__DIR__) . '/vendor/autoload.php';

/**
 * Builds an in-memory SQLite database seeded with sample `items`. Shared by the
 * examples so they run offline (needs ext-pdo_sqlite, a dev dependency).
 */
function example_db(): Connection
{
    $db = new Connection(new Driver('sqlite::memory:'), new SchemaCache(new ArrayCache()));

    $db->createCommand(
        'CREATE TABLE items (id INTEGER PRIMARY KEY, name TEXT, status TEXT, price INTEGER, created_at TEXT)',
    )->execute();

    $db->createCommand()->insertBatch('items', [
        [1, 'alpha', 'active', 10, '2024-01-01'],
        [2, 'bravo', 'active', 20, '2024-02-01'],
        [3, 'charlie', 'inactive', 30, '2024-03-01'],
        [4, 'delta', 'active', 40, '2024-04-01'],
        [5, 'echo', 'inactive', 50, '2024-05-01'],
    ], ['id', 'name', 'status', 'price', 'created_at'])->execute();

    return $db;
}

/**
 * @param array<array<string, mixed>> $rows
 */
function print_rows(string $title, array $rows): void
{
    echo $title . "\n";
    foreach ($rows as $row) {
        printf("  #%s  %-8s %-9s price=%s\n", $row['id'], $row['name'], $row['status'], $row['price']);
    }
    echo "\n";
}
