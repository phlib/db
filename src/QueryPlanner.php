<?php

namespace Phlib\Db;

use Phlib\Db\Adapter;

/**
 * Class QueryPlanner
 * @package Phlib\Db
 */
class QueryPlanner
{
    /**
     * @var Adapter
     */
    protected $adapter;

    /**
     * @var string
     */
    protected $select;

    /**
     * @var array
     */
    protected $bind;

    /**
     * QueryPlanner constructor.
     * @param Adapter $adapter
     * @param string $select
     * @param array $bind
     */
    public function __construct(Adapter $adapter, $select, array $bind = array())
    {
        $this->adapter = $adapter;
        $this->select  = $select;
        $this->bind    = $bind;
    }

    /**
     * @return array
     */
    public function getPlan()
    {
        return $this->adapter
            ->query("EXPLAIN {$this->select}", $this->bind)
            ->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * @return int
     */
    public function getNumberOfRowsInspected()
    {
        $inspectedRows = 1;
        foreach ($this->getPlan() as $analysis) {
            $inspectedRows *= (int)$analysis['rows'];

            // when exceeding PHPs integer max, it becomes a float
            if (is_float($inspectedRows)) {
                $inspectedRows = PHP_INT_MAX;
                break;
            }
        }

        return $inspectedRows;
    }
}
