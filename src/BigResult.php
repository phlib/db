<?php

namespace Phlib\Db;

use Phlib\Db\Adapter;
use Phlib\Db\Exception\InvalidArgumentException;

/**
 * Class BigResult
 * @package Phlib\Db
 */
class BigResult
{
    /**
     * @var Adapter
     */
    protected $adapter;

    /**
     * @var array
     */
    protected $options;

    /**
     * @param Adapter $adapter
     * @param array $options
     */
    public function __construct(Adapter $adapter, array $options = [])
    {
        $this->adapter = $adapter;
        $this->options = $options + [
            'long_query_time'   => 7200,
            'net_write_timeout' => 7200
        ];
    }

    /**
     * @param Adapter $adapter
     * @param string $select
     * @param array $bind
     * @param null $rowLimit
     * @return \PDOStatement
     */
    public static function execute(Adapter $adapter, $select, array $bind = [], $rowLimit = null)
    {
        return (new static($adapter))->query($select, $bind, $rowLimit);
    }

    /**
     * Execute query and return the unbuffered statement.
     *
     * @param string $select
     * @param array $bind
     * @param int $inspectedRowLimit
     * @return \PDOStatement
     */
    public function query($select, array $bind = [], $inspectedRowLimit = null)
    {
        if ($inspectedRowLimit !== null) {
            $inspectedRows = (new QueryPlanner($this->adapter, $select, $bind))
                ->getNumberOfRowsInspected();
            if ($inspectedRows > $inspectedRowLimit) {
                throw new InvalidArgumentException('');
            }
        }

        $longQueryTime   = $this->options['long_query_time'];
        $netWriteTimeout = $this->options['net_write_timeout'];

        $adapter = clone $this->adapter;
        $adapter->query("SET @@long_query_time={$longQueryTime}, @@net_write_timeout={$netWriteTimeout}");
        $adapter->disableBuffering();

        $stmt = $adapter->prepare($select);
        $stmt->execute($bind);

        return $stmt;
    }
}
