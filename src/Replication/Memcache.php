<?php

namespace Phlib\Db\Replication;

/**
 * Class Memcache
 */
class Memcache implements StorageInterface
{
    /**
     * @var Memcache
     */
    protected $memcache;

    /**
     * @param \Memcache $memcache
     */
    public function __construct(\Memcache $memcache)
    {
        $this->memcache = $memcache;
    }

    /**
     * @param array $memcacheConfig
     * @return static
     */
    public static function createFromConfig(array $memcacheConfig)
    {
        $memcache = new \Memcache();
        $memcache->connect($memcacheConfig['host'], $memcacheConfig['port'], $memcacheConfig['timeout']);
        return new static($memcache);
    }

    /**
     * @param string $host
     * @return string
     */
    public function getKey($host)
    {
        return "DbReplication:$host";
    }

    /**
     * @inheritdoc
     */
    public function getSecondsBehind($host)
    {
        $key = $this->getKey($host) . ':SecondsBehind';
        return $this->memcache->get($key);
    }

    /**
     * @inheritdoc
     */
    public function setSecondsBehind($host, $value)
    {
        $key = $this->getKey($host) . ':SecondsBehind';
        return $this->memcache->set($key, (int)$value);
    }

    /**
     * @inheritdoc
     */
    public function getHistory($host)
    {
        $key = $this->getKey($host) . ':History';
        return unserialize($this->memcache->get($key));
    }

    /**
     * @inheritdoc
     */
    public function setHistory($host, array $values)
    {
        $key = $this->getKey($host) . ':History';
        return $this->memcache->set($key, serialize($values));
    }
}
