<?php

namespace Phlib\Db\Replication;

/**
 * Interface StorageInterface
 * @package Phlib\Db\Replication
 */
interface StorageInterface
{
    /**
     * @return integer
     */
    public function getSecondsBehind($host);

    /**
     * @param string $host
     * @param integer $value
     * @return $this
     */
    public function setSecondsBehind($host, $value);

    /**
     * @return integer[]
     */
    public function getHistory($host);

    /**
     * @param string $host
     * @param integer[] $values
     * @return $this
     */
    public function setHistory($host, array $values);
}
