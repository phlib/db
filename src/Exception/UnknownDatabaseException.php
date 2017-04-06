<?php

namespace Phlib\Db\Exception;

class UnknownDatabaseException extends RuntimeException implements Exception
{
    /**
     * http://dev.mysql.com/doc/refman/5.5/en/error-messages-server.html
     *
     * ::construct - dbname=<dbname>
     * Code: 1049
     * Err:  SQLSTATE[HY000] [1049] Unknown database '<dbname>'
     *
     * ::query USE <dbname>
     * Code: 42000
     * Err:  SQLSTATE[42000]: Syntax error or access violation: 1049 Unknown database '<dbname>'
     */
    const ER_BAD_DB_ERROR_1 = 1049;
    const ER_BAD_DB_ERROR_2 = 42000;

    /**
     * @var string
     */
    private $name;

    /**
     * @param string $database
     * @param \PDOException $exception
     * @return static
     */
    public static function createFromUnknownDatabase($database, \PDOException $exception)
    {
        return new static($database, "Unknown database '{$database}'.", self::ER_BAD_DB_ERROR_1, $exception);
    }

    /**
     * @param \PDOException $exception
     * @return bool
     */
    public static function isUnknownDatabase(\PDOException $exception)
    {
        return $exception->getCode() == self::ER_BAD_DB_ERROR_1 ||
        (
            $exception->getCode() == self::ER_BAD_DB_ERROR_2 &&
            preg_match('/SQLSTATE\[42000\].*\s1049\s/', $exception->getMessage()) != false
        );
    }

    /**
     * UnknownDatabaseException constructor.
     * @param string $database
     * @param string $message
     * @param int $code
     * @param \PDOException|null $previous
     */
    public function __construct($database, $message, $code = 0, \PDOException $previous = null)
    {
        $this->name = $database;
        parent::__construct($message, $code, $previous);
    }

    /**
     * @return string
     */
    public function getDatabaseName()
    {
        return $this->name;
    }
}
