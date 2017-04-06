<?php

namespace Phlib\Db;

use Phlib\Db\Exception\UnknownDatabaseException;

interface AdapterInterface
{
    /**
     * Sets the item which creates a new DB connection.
     * @param callable $factory
     * @return $this
     */
    public function setConnectionFactory(callable $factory);

    /**
     * Get the database connection.
     *
     * @return \PDO
     */
    public function getConnection();

    /**
     * Set the database connection.
     *
     * @param \PDO $connection
     * @return $this
     */
    public function setConnection(\PDO $connection);

    /**
     * Reconnects the database connection.
     *
     * @return $this
     */
    public function reconnect();

    /**
     * Close connection
     *
     * @return void
     */
    public function closeConnection();

    /**
     * Clone connection
     *
     * @return \PDO
     */
    public function cloneConnection();

    /**
     * Magic method to clone the object.
     */
    public function __clone();

    /**
     * Get the config for the database connection. This could be empty if the
     * object was created with an empty array.
     *
     * @return array
     */
    public function getConfig();

    /**
     * Set database
     *
     * @param string $dbname
     * @return $this
     * @throws UnknownDatabaseException
     */
    public function setDatabase($dbname);

    /**
     * Set the character set on the connection.
     *
     * @param string $charset
     * @return $this
     */
    public function setCharset($charset);

    /**
     * Set the timezone on the connection.
     *
     * @param string $timezone
     * @return $this
     */
    public function setTimezone($timezone);

    /**
     * Enable connection buffering on queries.
     *
     * @return $this
     */
    public function enableBuffering();

    /**
     * Disable connection buffering on queries.
     *
     * @return $this
     */
    public function disableBuffering();

    /**
     * Returns whether the connection is set to buffered or not. By default
     * it's true, all results are buffered.
     *
     * @return boolean
     */
    public function isBuffered();

    /**
     * Ping the database connection to make sure the connection is still alive.
     *
     * @return boolean
     */
    public function ping();

    /**
     * Get the last inserted id. If the tablename is provided the id returned is
     * the last insert id will be for that table.
     *
     * @param string $tablename
     * @return integer
     */
    public function lastInsertId($tablename = null);

    /**
     * Prepare an SQL statement for execution.
     *
     * @param string $statement
     * @return \PDOStatement
     */
    public function prepare($statement);

    /**
     * Execute an SQL statement
     *
     * @param string $statement
     * @param array $bind
     * @return int
     */
    public function execute($statement, array $bind = []);

    /**
     * Query the database.
     *
     * @param string $sql
     * @param array $bind
     * @throws \PDOException
     * @return \PDOStatement
     */
    public function query($sql, array $bind = []);

    /**
     * @return bool
     */
    public function beginTransaction();

    /**
     * @return bool
     */
    public function commit();

    /**
     * @return bool
     */
    public function rollBack();
}
