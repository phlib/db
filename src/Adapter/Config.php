<?php

namespace Phlib\Db\Adapter;

use Phlib\Db\Exception\InvalidArgumentException;

class Config
{
    /**
     * @var array
     */
    private $config;

    /**
     * Config constructor.
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->config = $config + [
            'charset'  => 'utf8mb4',
            'timezone' => '+0:00'
        ];
    }

    /**
     * @return string
     * @throws InvalidArgumentException
     */
    public function getDsn()
    {
        if (!isset($this->config['host'])) {
            throw new InvalidArgumentException('Missing host config param');
        }

        $dsn = "mysql:host={$this->config['host']}";
        if (isset($this->config['port'])) {
            $dsn .= ";port={$this->config['port']}";
        }
        if (isset($this->config['dbname'])) {
            $dsn .= ";dbname={$this->config['dbname']}";
        }
        return $dsn;
    }

    /**
     * @return string
     */
    public function getUsername()
    {
        return isset($this->config['username']) ? $this->config['username'] : '';
    }

    /**
     * @return string
     */
    public function getPassword()
    {
        return isset($this->config['password']) ? $this->config['password'] : '';
    }

    /**
     * @return array
     */
    public function getOptions()
    {
        $timeoutValue   = isset($this->config['timeout']) ? $this->config['timeout'] : '';
        $timeoutOptions = ['options' => ['min_range' => 0, 'max_range' => 120, 'default' => 2]];
        $timeout        = filter_var($timeoutValue, FILTER_VALIDATE_INT, $timeoutOptions);
        return [
            \PDO::ATTR_TIMEOUT            => $timeout,
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC
        ];
    }

    /**
     * @return string
     */
    public function getDatabase()
    {
        return isset($this->config['dbname'])  ? $this->config['dbname']  : '';
    }

    /**
     * @param string $name
     * @return $this
     */
    public function setDatabase($name)
    {
        $this->config['dbname'] = $name;
        return $this;
    }

    /**
     * @return string
     */
    public function getCharset()
    {
        return isset($this->config['charset'])  ? $this->config['charset']  : 'utf8mb4';
    }

    /**
     * @param string $value
     * @return $this
     */
    public function setCharset($value)
    {
        $this->config['charset'] = $value;
        return $this;
    }

    /**
     * @return string
     */
    public function getTimezone()
    {
        return isset($this->config['timezone']) ? $this->config['timezone'] : '+0:00';
    }

    /**
     * @param string $timezone
     * @return $this
     */
    public function setTimezone($timezone)
    {
        $this->config['timezone'] = $timezone;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getMaximumAttempts()
    {
        $retryValue   = isset($this->config['retryCount']) ? $this->config['retryCount'] : 0;
        $retryOptions = ['options' => ['min_range' => 0, 'max_range' => 10, 'default' => 0]];
        $retryCount   = filter_var($retryValue, FILTER_VALIDATE_INT, $retryOptions);
        return $retryCount + 1;
    }

    /**
     * @return array
     */
    public function toArray()
    {
        return $this->config;
    }
}
