<?php

declare(strict_types=1);

namespace Rasuvaeff\Specification\Tests;

use Closure;
use Yiisoft\Db\Command\CommandInterface;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Connection\ServerInfoInterface;
use Yiisoft\Db\Expression\ExpressionInterface;
use Yiisoft\Db\Query\BatchQueryResultInterface;
use Yiisoft\Db\Query\QueryInterface;
use Yiisoft\Db\QueryBuilder\QueryBuilderInterface;
use Yiisoft\Db\Schema\Column\ColumnFactoryInterface;
use Yiisoft\Db\Schema\QuoterInterface;
use Yiisoft\Db\Schema\SchemaInterface;
use Yiisoft\Db\Schema\TableSchemaInterface;
use Yiisoft\Db\Transaction\TransactionInterface;

/**
 * @internal
 */
final class FakeConnection implements ConnectionInterface
{
    public function beginTransaction(?string $isolationLevel = null): TransactionInterface
    {
        throw new \BadMethodCallException('Not implemented');
    }

    public function createBatchQueryResult(QueryInterface $query): BatchQueryResultInterface
    {
        throw new \BadMethodCallException('Not implemented');
    }

    public function createCommand(?string $sql = null, array $params = []): CommandInterface
    {
        throw new \BadMethodCallException('Not implemented');
    }

    public function createQuery(): QueryInterface
    {
        throw new \BadMethodCallException('Not implemented');
    }

    public function createTransaction(): TransactionInterface
    {
        throw new \BadMethodCallException('Not implemented');
    }

    public function close(): void {}

    public function getColumnBuilderClass(): string
    {
        throw new \BadMethodCallException('Not implemented');
    }

    public function getColumnFactory(): ColumnFactoryInterface
    {
        throw new \BadMethodCallException('Not implemented');
    }

    public function getDriverName(): string
    {
        return 'fake';
    }

    public function getLastInsertId(?string $sequenceName = null): string
    {
        throw new \BadMethodCallException('Not implemented');
    }

    public function getQueryBuilder(): QueryBuilderInterface
    {
        throw new \BadMethodCallException('Not implemented');
    }

    public function getQuoter(): QuoterInterface
    {
        throw new \BadMethodCallException('Not implemented');
    }

    public function getSchema(): SchemaInterface
    {
        throw new \BadMethodCallException('Not implemented');
    }

    public function getServerInfo(): ServerInfoInterface
    {
        throw new \BadMethodCallException('Not implemented');
    }

    public function getTablePrefix(): string
    {
        return '';
    }

    public function getTableSchema(string $name, bool $refresh = false): ?TableSchemaInterface
    {
        throw new \BadMethodCallException('Not implemented');
    }

    public function getTransaction(): ?TransactionInterface
    {
        return null;
    }

    public function isActive(): bool
    {
        return false;
    }

    public function isSavepointEnabled(): bool
    {
        return false;
    }

    public function open(): void {}

    public function quoteValue(mixed $value): mixed
    {
        throw new \BadMethodCallException('Not implemented');
    }

    public function setEnableSavepoint(bool $value): void {}

    public function select(
        array|bool|float|int|string|ExpressionInterface $columns = [],
        ?string $option = null,
    ): QueryInterface {
        throw new \BadMethodCallException('Not implemented');
    }

    public function setTablePrefix(string $value): void {}

    public function transaction(Closure $closure, ?string $isolationLevel = null): mixed
    {
        throw new \BadMethodCallException('Not implemented');
    }
}
