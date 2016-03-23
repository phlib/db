<?php

namespace Phlib\Db;

use Phlib\Db\Exception\InvalidQueryException;
use Phlib\Db\Exception\UnknownDatabaseException;
use Phlib\Db\Exception\RuntimeException;
use Phlib\Db\Exception\InvalidArgumentException;

/**
 * Database Adapter
 *
 * @method string quote() quote(string $value, integer $type = null)
 * @method string quoteInto() quoteInto(string $text, mixed $value, int $type = null)
 * @method string quoteColumnAs() quoteColumnAs(string $ident, string $alias, bool $auto = false)
 * @method string quoteTableAs() quoteTableAs(string $ident, string $alias = null, bool $auto = false)
 * @method string quoteIdentifier() quoteIdentifier(string $ident, bool $auto = false)
 *
 * @method \PDOStatement select() select(string $table, string $where = '', array $bind = array())
 * @method \PDOStatement insert() insert(string $table, array $data)
 * @method \PDOStatement update() update(string $table, array $data, string $where = '', array $bind = array())
 * @method \PDOStatement delete() delete(string $table, string $where = '', array $bind = array())
 */
class Adapter
{
    // Message: Unknown database '%s'
    const ER_BAD_DB_ERROR = 1049; // http://dev.mysql.com/doc/refman/5.5/en/error-messages-server.html

    /**
     * @var array
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
        $this->config = $config + array(
            'charset'  => 'utf8mb4',
            'timezone' => '+0:00'
        );
        $this->quoter = new Adapter\QuoteHandler(function($value, $type) {
            return $this->getConnection()->quote($value, $type);
        });
        $this->crud = new Adapter\Crud($this);
        $this->connectionFactory = function(array $config) {
            return $this->createConnection($config);
        };
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
     * @param Adapter\Crud $crud
     * @return $this
     */
    public function setCrudHelper(Adapter\Crud $crud)
    {
        $this->crud = $crud;
        return $this;
    }

    /**
     * @param string $name
     * @param array $arguments
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        if (method_exists($this->quoter, $name)) {
            return call_user_func_array([$this->quoter, $name], $arguments);
        }
        if (method_exists($this->crud, $name)) {
            return call_user_func_array([$this->crud, $name], $arguments);
        }
        throw new \BadMethodCallException("Specified method '$name' is not known.");
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
        $this->config['dbname'] = $dbname;
        if ($this->connection) {
            try {
                $this->query('USE ' . $this->quoter->quoteIdentifier($dbname));
            } catch (RuntimeException $e) {
                if ($e->getCode() !== self::ER_BAD_DB_ERROR &&
                    preg_match('/SQLSTATE\[42000\].*\w1049\w/', $e->getMessage()) !== false
                ) {
                    throw new UnknownDatabaseException("Unknown database '{$dbname}'", self::ER_BAD_DB_ERROR, $e);
                }

                throw $e;
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
        return $this->config;
    }

    /**
     * Get a configuration value.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function getConfigValue($key, $default = null)
    {
        if (array_key_exists($key, $this->config)) {
            return $this->config[$key];
        }
        return $default;
    }

    /**
     * Set the character set on the connection.
     *
     * @param string $charset
     * @return Adapter
     */
    public function setCharset($charset)
    {
        if ($this->getConfigValue('charset') !== $charset) {
            $this->config['charset'] = $charset;
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
        if ($this->getConfigValue('timezone') !== $timezone) {
            $this->config['timezone'] = $timezone;
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
            if (InvalidQueryException::matches($exception)) {
                throw new InvalidQueryException($sql, $bind, $exception);
            } elseif (stripos($exception->getMessage(), 'MySQL server has gone away') !== false) {
                $this->reconnect();
                return $this->doQuery($sql, $bind, true);
            } else {
                throw new RuntimeException($exception->getMessage(), $exception->getCode(), $exception);
            }
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
            $this->connection = call_user_func($this->connectionFactory, $this->getConfig());
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
        return call_user_func($this->connectionFactory, $this->getConfig());
    }

    /**
     * Create connection
     *
     * @param array $config
     * @return \PDO
     * @throws InvalidArgumentException
     */
    protected function createConnection($config)
    {
        if (!isset($config['host'])) {
            throw new InvalidArgumentException('Missing host config param');
        }

        $dsn = "mysql:host={$config['host']}";
        if (isset($config['port'])) {
            $dsn .= ";port={$config['port']}";
        }
        if (isset($config['dbname'])) {
            $dsn .= ";dbname={$config['dbname']}";
        }

        $timeoutOptions = ['options' => ['min_range' => 0, 'max_range' => 120, 'default' => 2]];
        $timeout = filter_var($this->getConfigValue('timeout'), FILTER_VALIDATE_INT, $timeoutOptions);

        $retryOptions = ['options' => ['min_range' => 0, 'max_range' => 10, 'default' => 0]];
        $retryCount = filter_var($this->getConfigValue('retryCount'), FILTER_VALIDATE_INT, $retryOptions);
        $maxAttempts = $retryCount + 1;

        $username = isset($config['username']) ? $config['username'] : '';
        $password = isset($config['password']) ? $config['password'] : '';
        $charset  = isset($config['charset'])  ? $config['charset']  : 'utf8mb4';
        $timezone = isset($config['timezone']) ? $config['timezone'] : '+0:00';
        $options  = [
            \PDO::ATTR_TIMEOUT            => $timeout,
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC
        ];

        $attempt = 0;
        while (++$attempt <= $maxAttempts) {
            try {
                $connection = new \PDO($dsn, $username, $password, $options);
                $statement  = $connection->prepare('SET NAMES ?, time_zone = ?');
                $statement->execute(array($charset, $timezone));

                return $connection;

            } catch (\PDOException $e) {
                if ($e->getCode() == self::ER_BAD_DB_ERROR) {
                    // unknown database, no need to continue with retries this is conclusive
                    throw new UnknownDatabaseException(
                        sprintf("Unknown database '%s'", $config['dbname']),
                        $e->getCode(),
                        $e
                    );
                }

                if ($maxAttempts > $attempt) {
                    // more tries left, so we'll log this error
                    error_log(
                        sprintf(
                            'Failed connection to "%s" on attempt %d with error "%s"',
                            $dsn,
                            $attempt,
                            $e->getMessage()
                        )
                    );

                    // sleep with some exponential backoff
                    $msec = pow(2, $attempt) * 50;
                    usleep($msec * 1000);
                } else {
                    // ran out of attempts, throw the last error
                    throw new RuntimeException($e->getMessage(), $e->getCode(), $e);
                }
            }
        }
    }

    /**
     * @return bool
     */
    public function beginTransaction()
    {
        try {
            return $this->getConnection()->beginTransaction();
        } catch (\PDOException $exception) {
            if (stripos($exception->getMessage(), 'MySQL server has gone away') !== false) {
                $this->reconnect();
                return $this->getConnection()->beginTransaction();
            }
            throw $exception;
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
