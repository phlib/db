<?php

declare(strict_types=1);

namespace Phlib\Db;

interface AdapterInterface
{
    public function setConnectionFactory(callable $factory): self;

    public function getConnection(): \PDO;

    public function setConnection(\PDO $connection): self;

    public function reconnect(): self;

    public function closeConnection(): void;

    public function cloneConnection(): \PDO;

    public function __clone();

    public function getConfig(): array;

    public function setDatabase(string $dbname): self;

    public function setCharset(string $charset): self;

    public function setTimezone(string $timezone): self;

    public function enableBuffering(): self;

    public function disableBuffering(): self;

    public function isBuffered(): bool;

    public function ping(): bool;

    /**
     * Get the last inserted id. If the tablename is provided the id returned is
     * the last insert id will be for that table.
     */
    public function lastInsertId(?string $tablename = null): string;

    public function prepare(string $statement): \PDOStatement;

    public function execute(string $statement, array $bind = []): int;

    public function query(string $sql, array $bind = []): \PDOStatement;

    public function beginTransaction(): bool;

    public function commit(): bool;

    public function rollBack(): bool;
}
