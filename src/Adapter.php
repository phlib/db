<?php

declare(strict_types=1);

namespace Phlib\Db;

use Phlib\Db\Exception\InvalidQueryException;
use Phlib\Db\Exception\RuntimeException;
use Phlib\Db\Exception\UnknownDatabaseException;

class Adapter implements AdapterInterface
{
    use Adapter\CrudTrait;

    private Adapter\Config $config;

    private \PDO $connection;

    /**
     * @var callable
     */
    private $connectionFactory;

    private Adapter\QuoteHandler $quoter;

    /**
     * @param array $config {
     *   @var string $host required
     *   @var string $username required
     *   @var string $password required
     *   @var int $port optional
     *   @var string $dbname optional
     * }
     */
    public function __construct(array $config = [])
    {
        $this->config = new Adapter\Config($config);
        $this->connectionFactory = new Adapter\ConnectionFactory();
    }

    public function quote(): Adapter\QuoteHandler
    {
        if (!isset($this->quoter)) {
            $this->quoter = new Adapter\QuoteHandler(function (string $value): string {
                return $this->getConnection()->quote($value);
            });
        }

        return $this->quoter;
    }

    public function setConnectionFactory(callable $factory): self
    {
        $this->connectionFactory = $factory;
        return $this;
    }

    public function __clone()
    {
        // close our existing connection, we'll create a new one when we need it
        $this->closeConnection();
    }

    public function closeConnection(): void
    {
        unset($this->connection);
    }

    public function reconnect(): self
    {
        unset($this->connection);
        $this->connect();

        return $this;
    }

    public function getConnection(): \PDO
    {
        $this->connect();

        return $this->connection;
    }

    public function setConnection(\PDO $connection): self
    {
        $this->connection = $connection;

        return $this;
    }

    public function setDatabase(string $dbname): self
    {
        $this->config->setDatabase($dbname);
        if (isset($this->connection)) {
            try {
                $this->query('USE ' . $this->quote()->identifier($dbname));
            } catch (RuntimeException $exception) {
                /** @var \PDOException $prevException */
                $prevException = $exception->getPrevious();
                if (UnknownDatabaseException::isUnknownDatabase($prevException)) {
                    throw UnknownDatabaseException::createFromUnknownDatabase($dbname, $prevException);
                }

                throw $exception;
            }
        }

        return $this;
    }

    /**
     * Get the config for the database connection. This could be empty if the
     * object was created with an empty array.
     */
    public function getConfig(): array
    {
        return $this->config->toArray();
    }

    public function setCharset(string $charset): self
    {
        if ($this->config->getCharset() !== $charset) {
            $this->config->setCharset($charset);
            if (isset($this->connection)) {
                $this->query('SET NAMES ?', [$charset]);
            }
        }

        return $this;
    }

    public function setTimezone(string $timezone): self
    {
        if ($this->config->getTimezone() !== $timezone) {
            $this->config->setTimezone($timezone);
            if (isset($this->connection)) {
                $this->query('SET time_zone = ?', [$timezone]);
            }
        }

        return $this;
    }

    public function enableBuffering(): self
    {
        return $this->setBuffering(true);
    }

    public function disableBuffering(): self
    {
        return $this->setBuffering(false);
    }

    public function isBuffered(): bool
    {
        return (bool)$this->getConnection()
            ->getAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY);
    }

    private function setBuffering(bool $enabled): self
    {
        $this->getConnection()
            ->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, $enabled);
        return $this;
    }

    public function ping(): bool
    {
        try {
            return $this->query('SELECT "1"')->fetchColumn() === '1';
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * Get the last inserted id. If the tablename is provided the id returned is
     * the last insert id will be for that table.
     */
    public function lastInsertId(?string $tablename = null): string
    {
        // the lastInsertId is cached from the last insert, so no point in detected disconnection
        return $this->getConnection()->lastInsertId($tablename);
    }

    public function prepare(string $sql): \PDOStatement
    {
        return $this->doQuery($sql, null);
    }

    public function execute(string $sql, array $bind = []): int
    {
        $stmt = $this->query($sql, $bind);
        return $stmt->rowCount();
    }

    public function query(string $sql, array $bind = []): \PDOStatement
    {
        return $this->doQuery($sql, $bind);
    }

    private function doQuery(string $sql, ?array $bind, bool $hasCaughtException = false): \PDOStatement
    {
        try {
            $stmt = $this->getConnection()->prepare($sql);
            if ($bind !== null) {
                $stmt->execute($bind);
            }
            return $stmt;
        } catch (\PDOException $exception) {
            if (InvalidQueryException::isInvalidSyntax($exception)) {
                throw new InvalidQueryException($sql, $bind, $exception);
            } elseif (RuntimeException::hasServerGoneAway($exception) && !$hasCaughtException) {
                $this->reconnect();
                return $this->doQuery($sql, $bind, true);
            }
            throw RuntimeException::createFromException($exception);
        }
    }

    private function connect(): self
    {
        if (!isset($this->connection)) {
            $this->connection = call_user_func($this->connectionFactory, $this->config);
        }

        return $this;
    }

    public function cloneConnection(): \PDO
    {
        return call_user_func($this->connectionFactory, $this->config);
    }

    public function beginTransaction(): bool
    {
        return $this->doBeginTransaction();
    }

    private function doBeginTransaction(bool $hasCaughtException = false): bool
    {
        try {
            return $this->getConnection()->beginTransaction();
        } catch (\PDOException $exception) {
            if (RuntimeException::hasServerGoneAway($exception) && !$hasCaughtException) {
                $this->reconnect();
                return $this->doBeginTransaction(true);
            }
            throw RuntimeException::createFromException($exception);
        }
    }

    public function commit(): bool
    {
        return $this->getConnection()->commit();
    }

    public function rollBack(): bool
    {
        return $this->getConnection()->rollBack();
    }
}
