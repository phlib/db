<?php

declare(strict_types=1);

namespace Phlib\Db\Exception;

class UnknownDatabaseException extends RuntimeException implements Exception
{
    /**
     * http://dev.mysql.com/doc/refman/5.5/en/error-messages-server.html
     *
     * ::construct - dbname=<dbname>
     * Code: 1049
     * Err:  SQLSTATE[HY000] [1049] Unknown database '<dbname>'
     */
    public const ER_BAD_DB_ERROR_1 = 1049;

    /**
     * http://dev.mysql.com/doc/refman/5.5/en/error-messages-server.html
     *
     * ::query USE <dbname>
     * Code: 42000
     * Err:  SQLSTATE[42000]: Syntax error or access violation: 1049 Unknown database '<dbname>'
     */
    public const ER_BAD_DB_ERROR_2 = 42000;

    private string $name;

    public static function createFromUnknownDatabase(string $database, \PDOException $exception): self
    {
        return new static($database, "Unknown database '{$database}'.", self::ER_BAD_DB_ERROR_1, $exception);
    }

    public static function isUnknownDatabase(\PDOException $exception): bool
    {
        return (int)$exception->getCode() === self::ER_BAD_DB_ERROR_1 ||
        (
            (int)$exception->getCode() === self::ER_BAD_DB_ERROR_2 &&
            preg_match('/SQLSTATE\[42000\].*\s1049\s/', $exception->getMessage()) === 1
        );
    }

    public function __construct(string $database, string $message, int $code = 0, ?\PDOException $previous = null)
    {
        $this->name = $database;
        parent::__construct($message, $code, $previous);
    }

    public function getDatabaseName(): string
    {
        return $this->name;
    }
}
