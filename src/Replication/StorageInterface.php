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
    public function getSecondsBehind();

    /**
     * @param string $host
     * @param integer $value
     * @return $this
     */
    public function setSecondsBehind($host, $value);

    /**
     * @return integer[]
     */
    public function getHistory();

    /**
     * @param string $host
     * @param integer[] $values
     * @return $this
     */
    public function setHistory($host, array $values);
}
