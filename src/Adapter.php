<?php

namespace Phlib\Db;

use Phlib\Db\Exception\InvalidQueryException;
use Phlib\Db\Exception\UnknownDatabaseException;
use Phlib\Db\Exception\RuntimeException;
use Phlib\Db\Exception\InvalidArgumentException;

/**
 * Database Adapter
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
     * @var boolean
     */
    protected $autoQuoteIdentifiers = true;

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
                $this->query('USE ' . $this->quoteIdentifier($dbname));
            } catch (\PDOException $e) {
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
     * Set the character set on the connection.
     *
     * @param string $charset
     * @return Adapter
     */
    public function setCharset($charset)
    {
        if (array_get($this->config, 'charset') !== $charset) {
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
        if (array_get($this->config, 'timezone') !== $timezone) {
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
    public function exec($statement, array $bind = array())
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
        try {
            $stmt = $this->getConnection()->prepare($sql);
            $stmt->execute($bind);
        } catch (\PDOException $exception) {
            if (InvalidQueryException::matches($exception)) {
                throw new InvalidQueryException($sql, $bind, $exception);
            } elseif (stripos($exception->getMessage(), 'MySQL server has gone away') !== false) {
                $this->reconnect();

                $stmt = $this->getConnection()->prepare($sql);
                $stmt->execute($bind);
            } else {
                throw new RuntimeException($exception->getMessage(), $exception->getCode(), $exception);
            }
        }

        return $stmt;
    }

    /**
     * Quote a database value.
     *
     * @param string $value
     * @param integer $type
     * @return string
     * @throws InvalidArgumentException
     */
    public function quote($value, $type = null)
    {
        switch (true) {
            case is_object($value):
                if (!method_exists($value, '__toString')) {
                    throw new InvalidArgumentException('Object can not be converted to string value.');
                }
                $value = (string)$value;
                break;
            case is_bool($value):
                $value = (int)$value;
                break;
            case (is_scalar($value) && (string)($value + 0) === (string)$value):
                $value = $value + 0;
                break;
            case is_null($value):
                $value = 'NULL';
                break;
            case is_array($value):
                array_walk($value, array($this, 'quoteByRef'));
                $value = implode(', ', $value);
                break;
            default:
                $value = $this->getConnection()->quote($value, $type);
        }

        return $value;
    }

    /**
     * Quote by ref
     *
     * Replaces the value with a quoted version of the value
     *
     * @param mixed $value
     * @return void
     */
    public function quoteByRef(&$value)
    {
        if (is_array($value)) {
            $value = 'Array';
        }
        $value = $this->quote($value);
    }

    /**
     * Quote into the value for the database.
     *
     * @param string $text
     * @param mixed $value
     * @param string $type
     * @return string
     */
    public function quoteInto($text, $value, $type = null)
    {
        return str_replace('?', $this->quote($value, $type), $text);
    }

    /**
     * Quote a column identifier and alias.
     *
     * @param string|array $ident
     * @param string $alias
     * @param boolean $auto
     * @return string
     */
    public function quoteColumnAs($ident, $alias, $auto = false)
    {
        return $this->quoteIdentifierAs($ident, $alias, $auto);
    }

    /**
     * Quote a table identifier and alias.
     *
     * @param string|array $ident
     * @param string $alias
     * @param boolean $auto
     * @return string
     */
    public function quoteTableAs($ident, $alias = null, $auto = false)
    {
        return $this->quoteIdentifierAs($ident, $alias, $auto);
    }

    /**
     * Select data from table.
     *
     * @param string $table
     * @param string $where
     * @param array $bind
     * @return \PDOStatement
     */
    public function select($table, $where = '', array $bind = array())
    {
        $table = $this->quoteIdentifier($table);
        $sql   = "SELECT * FROM $table "
            . (($where) ? " WHERE $where" : '');

        return $this->query($sql, $bind);
    }

    /**
     * Insert data to table.
     *
     * @param string $table
     * @param array $data
     * @return int Number of affected rows
     */
    public function insert($table, array $data)
    {
        $table  = $this->quoteIdentifier($table);
        $fields = implode(', ', array_keys($data));
        $placeHolders = implode(', ', array_fill(0, count($data), '?'));
        $sql = "INSERT INTO $table ($fields) VALUES ($placeHolders)";

        $stmt = $this->query($sql, array_values($data));

        return $stmt->rowCount();
    }

    /**
     * Update data in table.
     *
     * @param string $table
     * @param array $data
     * @param string $where
     * @param array $bind
     * @return int|boolean Number of affected rows
     */
    public function update($table, array $data, $where = '', array $bind = array())
    {
        $table  = $this->quoteIdentifier($table);
        $fields = array();
        foreach (array_keys($data) as $field) {
            $fields[] = "$field = ?";
        }
        $sql = "UPDATE $table SET " . implode(', ', $fields)
            . (($where) ? " WHERE $where" : '');

        $stmt = $this->query($sql, array_merge(array_values($data), $bind));

        return $stmt->rowCount();
    }

    /**
     * Delete from table.
     *
     * @param string $table
     * @param string $where
     * @param array $bind
     * @return int Number of affected rows
     */
    public function delete($table, $where = '', array $bind = array())
    {
        $table = $this->quoteIdentifier($table);
        $sql   = "DELETE FROM $table"
            . (($where) ? " WHERE $where" : '');

        $stmt = $this->query($sql, $bind);

        return $stmt->rowCount();
    }

    /**
     * Quotes an identifier
     *
     * @param string|array $ident
     * @param boolean $auto
     * @return string
     */
    public function quoteIdentifier($ident, $auto = false)
    {
        return $this->quoteIdentifierAs($ident, null, $auto);
    }

    /**
     * Connect
     *
     * @return Adapter
     */
    protected function connect()
    {
        if (is_null($this->connection)) {
            $this->connection = $this->createConnection($this->config);
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
        return $this->createConnection($this->getConfig());
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

        $timeout = filter_var(
            array_get($config, 'timeout'),
            FILTER_VALIDATE_INT,
            array(
                'options' => array(
                    'default'   => 2,
                    'min_range' => 0,
                    'max_range' => 120
                )
            )
        );

        $retryCount = filter_var(
            array_get($config, 'retryCount'),
            FILTER_VALIDATE_INT,
            array(
                'options' => array(
                    'default'   => 0,
                    'min_range' => 0,
                    'max_range' => 10
                )
            )
        );
        $maxAttempts = $retryCount + 1;

        $options = array(
            \PDO::ATTR_TIMEOUT            => $timeout,
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC
        );

        $username = array_get($config, 'username', '');
        $password = array_get($config, 'password', '');
        $charset  = array_get($config, 'charset', 'utf8mb4');
        $timezone = array_get($config, 'timezone', '+0:00');

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
     * Quote an identifier and an optional alias.
     *
     * @param string|array|object $ident
     * @param string $alias
     * @param boolean $auto
     * @param string $as
     * @return string
     */
    protected function quoteIdentifierAs($ident, $alias = null, $auto = false, $as = ' AS ')
    {
        if (is_object($ident) && method_exists($ident, 'assemble')) {
            $quoted = '(' . $ident->assemble() . ')';
        } elseif (is_object($ident)) {
            if (!method_exists($ident, '__toString')) {
                throw new InvalidArgumentException('Object can not be converted to string identifier.');
            }
            $quoted = (string)$ident;
        } else {
            if (is_string($ident)) {
                $ident = explode('.', $ident);
            }
            if (is_array($ident)) {
                $segments = array();
                foreach ($ident as $segment) {
                    if (is_object($segment)) {
                        $segments[] = (string)$segment;
                    } else {
                        $segments[] = $this->performQuoteIdentifier($segment, $auto);
                    }
                }
                if ($alias !== null && end($ident) == $alias) {
                    $alias = null;
                }
                $quoted = implode('.', $segments);
            } else {
                $quoted = $this->performQuoteIdentifier($ident, $auto);
            }
        }

        if ($alias !== null) {
            $quoted .= $as . $this->performQuoteIdentifier($alias, $auto);
        }

        return $quoted;
    }

    /**
     * Quote an identifier.
     *
     * @param string $value
     * @param boolean $auto
     * @return string
     */
    protected function performQuoteIdentifier($value, $auto = false)
    {
        if ($auto === false || $this->autoQuoteIdentifiers === true) {
            $q = '`';
            return ($q . str_replace("$q", "$q$q", $value) . $q);
        }

        return $value;
    }

    /**
     * @return bool
     */
    public function beginTransaction()
    {
        return $this->getConnection()->beginTransaction();
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
