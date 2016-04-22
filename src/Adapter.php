<?php

namespace Phlib\Db;

use Phlib\Db\Adapter\AdapterInterface;
use Phlib\Db\Adapter\QuotableAdapterInterface;
use Phlib\Db\Adapter\QuotableInterface;
use Phlib\Db\Adapter\CrudInterface;
use Phlib\Db\Adapter\ConnectionFactory;
use Phlib\Db\Exception\InvalidQueryException;
use Phlib\Db\Exception\UnknownDatabaseException;
use Phlib\Db\Exception\RuntimeException;

/**
 * Class Adapter
 * @package Phlib\Db
 */
class Adapter implements QuotableAdapterInterface, CrudInterface
{
    /**
     * @var Adapter\Config
     */
    protected $config;

    /**
     * @var \PDO
     */
    protected $connection = null;

    /**
     * @var callable
     */
    protected $connectionFactory;

    /**
     * @var Adapter\QuoteHandler
     */
    protected $quoter;

    /**
     * @var Adapter\Crud
     */
    protected $crud;

    /**
     * Constructor
     *
     * === Config Params ===
     * host
     * username
     * password
     * [port]
     * [dbname]
     *
     * @param array $config
     */
    public function __construct(array $config = array())
    {
        $this->config = new Adapter\Config($config);
        $this->quoter = new Adapter\QuoteHandler(function($value, $type) {
            return $this->getConnection()->quote($value, $type);
        });
        $this->crud = new Adapter\Crud($this);
        $this->connectionFactory = new ConnectionFactory();
    }

    /**
     * @return Adapter\QuoteHandler
     */
    public function getQuoteHandler()
    {
        return $this->quoter;
    }

    /**
     * @param Adapter\QuoteHandler $quoteHandler
     * @return $this
     */
    public function setQuoteHandler(Adapter\QuoteHandler $quoteHandler)
    {
        $this->quoter = $quoteHandler;
        return $this;
    }

    /**
     * @return Adapter\QuoteHandler
     */
    public function getCrudHelper()
    {
        return $this->crud;
    }

    /**
     * @param mixed $value
     * @param int $type
     * @return string
     */
    public function quote($value, $type = null)
    {
        return $this->quoter->quote($value, $type);
    }

    /**
     * @param string $text
     * @param mixed $value
     * @param int $type
     * @return string
     */
    public function quoteInto($text, $value, $type = null)
    {
        return $this->quoter->quoteInto($text, $value, $type);
    }

    /**
     * @param string $ident
     * @param string $alias
     * @param bool $auto
     * @return string
     */
    public function quoteColumnAs($ident, $alias, $auto = false)
    {
        return $this->quoter->quoteColumnAs($ident, $alias, $auto);
    }

    /**
     * @param string $ident
     * @param string  $alias
     * @param bool $auto
     * @return string
     */
    public function quoteTableAs($ident, $alias = null, $auto = false)
    {
        return $this->quoter->quoteTableAs($ident, $alias, $auto);
    }

    /**
     * @param $ident
     * @param bool $auto
     * @return string
     */
    public function quoteIdentifier($ident, $auto = false)
    {
        return $this->quoter->quoteIdentifier($ident, $auto);
    }

    /**
     * @param Adapter\Crud $crud
     * @return $this
     */
    public function setCrudHelper(Adapter\Crud $crud)
    {
        $this->crud = $crud;
        return $this;
    }

    /**
     * @param string $table
     * @param string $where
     * @param array $bind
     * @return \PDOStatement
     */
    public function select($table, $where = '', array $bind = array())
    {
        return $this->crud->select($table, $where, $bind);
    }

    /**
     * @param string $table
     * @param array $data
     * @return int
     */
    public function insert($table, array $data)
    {
        return $this->crud->insert($table, $data);
    }

    /**
     * @param string $table
     * @param array $data
     * @param string $where
     * @param array $bind
     * @return int
     */
    public function update($table, array $data, $where = '', array $bind = array())
    {
        return $this->crud->update($table, $data, $where, $bind);
    }

    /**
     * @param string $table
     * @param string $where
     * @param array $bind
     * @return int
     */
    public function delete($table, $where = '', array $bind = array())
    {
        return $this->crud->delete($table, $where, $bind);
    }

    /**
     * Sets the item which creates a new DB connection.
     * @param callable $factory
     * @return $this
     */
    public function setConnectionFactory(callable $factory)
    {
        $this->connectionFactory = $factory;
        return $this;
    }

    /**
     * Magic method to clone the object.
     */
    public function __clone()
    {
        // close our existing connection, we'll create a new one when we need it
        $this->closeConnection();
    }

    /**
     * Close connection
     *
     * @return void
     */
    public function closeConnection()
    {
        $this->connection = null;
    }

    /**
     * Reconnects the database connection.
     *
     * @return Adapter
     */
    public function reconnect()
    {
        $this->connection = null;
        $this->connect();

        return $this;
    }

    /**
     * Get the database connection.
     *
     * @return \PDO
     */
    public function getConnection()
    {
        $this->connect();

        return $this->connection;
    }

    /**
     * Set the database connection.
     *
     * @param \PDO $connection
     * @return Adapter
     */
    public function setConnection(\PDO $connection)
    {
        $this->connection = $connection;

        return $this;
    }

    /**
     * Set database
     *
     * @param string $dbname
     * @return Adapter
     * @throws UnknownDatabaseException
     */
    public function setDatabase($dbname)
    {
        $this->config->setDatabase($dbname);
        if ($this->connection) {
            try {
                $this->query('USE ' . $this->quoter->quoteIdentifier($dbname));
            } catch (RuntimeException $exception) {
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
     *
     * @return array
     */
    public function getConfig()
    {
        return $this->config->toArray();
    }

    /**
     * Set the character set on the connection.
     *
     * @param string $charset
     * @return Adapter
     */
    public function setCharset($charset)
    {
        if ($this->config->getCharset() !== $charset) {
            $this->config->setCharset($charset);
            if ($this->connection) {
                $this->query('SET NAMES ?', array($charset));
            }
        }

        return $this;
    }

    /**
     * Set the timezone on the connection.
     *
     * @param string $timezone
     * @return Adapter
     */
    public function setTimezone($timezone)
    {
        if ($this->config->getTimezone() !== $timezone) {
            $this->config->setTimezone($timezone);
            if ($this->connection) {
                $this->query('SET time_zone = ?', array($timezone));
            }
        }

        return $this;
    }

    /**
     * Enable connection buffering on queries.
     *
     * @return Adapter
     */
    public function enableBuffering()
    {
        return $this->setBuffering(true);
    }

    /**
     * Disable connection buffering on queries.
     *
     * @return Adapter
     */
    public function disableBuffering()
    {
        return $this->setBuffering(false);
    }

    /**
     * Returns whether the connection is set to buffered or not. By default
     * it's true, all results are buffered.
     *
     * @return boolean
     */
    public function isBuffered()
    {
        return (bool)$this->getConnection()
            ->getAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY);
    }

    /**
     * Sets whether the connection is buffered or unbuffered. By default the
     * connection is buffered.
     *
     * @param boolean $enabled
     * @return Adapter
     */
    protected function setBuffering($enabled)
    {
        $this->getConnection()
            ->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, $enabled);
        return $this;
    }

    /**
     * Ping the database connection to make sure the connection is still alive.
     *
     * @return boolean
     */
    public function ping()
    {
        try {
            return ($this->query('SELECT 1')->fetchColumn() == 1);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get the last inserted id. If the tablename is provided the id returned is
     * the last insert id will be for that table.
     *
     * @param string $tablename
     * @return integer
     */
    public function lastInsertId($tablename = null)
    {
        // the lastInsertId is cached from the last insert, so no point in detected disconnection
        return $this->getConnection()->lastInsertId($tablename);
    }

    /**
     * Prepare an SQL statement for execution.
     *
     * @param string $statement
     * @return \PDOStatement
     */
    public function prepare($statement)
    {
        // the prepare method is emulated by PDO, so no point in detected disconnection
        return $this->getConnection()->prepare($statement);
    }

    /**
     * Execute an SQL statement
     *
     * @param string $statement
     * @param array $bind
     * @return int
     */
    public function execute($statement, array $bind = array())
    {
        $stmt = $this->query($statement, $bind);
        return $stmt->rowCount();
    }

    /**
     * Query the database.
     *
     * @param string $sql
     * @param array $bind
     * @throws \PDOException
     * @return \PDOStatement
     */
    public function query($sql, array $bind = array())
    {
        return $this->doQuery($sql, $bind);
    }

    /**
     * @param string $sql
     * @param array $bind
     * @param bool $hasCaughtException
     * @return \PDOStatement
     */
    protected function doQuery($sql, array $bind, $hasCaughtException = false)
    {
        try {
            $stmt = $this->getConnection()->prepare($sql);
            $stmt->execute($bind);
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

    /**
     * Connect
     *
     * @return Adapter
     */
    protected function connect()
    {
        if (is_null($this->connection)) {
            $this->connection = call_user_func($this->connectionFactory, $this->config);
        }

        return $this;
    }

    /**
     * Clone connection
     *
     * @return \PDO
     */
    public function cloneConnection()
    {
        return call_user_func($this->connectionFactory, $this->config);
    }

    /**
     * @return bool
     */
    public function beginTransaction()
    {
        return $this->doBeginTransaction();
    }

    /**
     * @param bool $hasCaughtException
     * @return bool
     */
    protected function doBeginTransaction($hasCaughtException = false)
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

    /**
     * @return bool
     */
    public function commit()
    {
        return $this->getConnection()->commit();
    }

    /**
     * @return bool
     */
    public function rollBack()
    {
        return $this->getConnection()->rollBack();
    }
}
