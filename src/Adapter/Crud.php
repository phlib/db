<?php

namespace Phlib\Db\Adapter;

use Phlib\Db\Adapter;

/**
 * Class Crud
 * @package Phlib\Db\Helper
 */
class Crud implements CrudInterface
{
    /**
     * @var Adapter
     */
    protected $adapter;

    /**
     * Crud constructor.
     * @param Adapter $adapter
     */
    public function __construct(Adapter $adapter)
    {
        $this->adapter = $adapter;
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
        $table = $this->adapter->quoteIdentifier($table);
        $sql   = "SELECT * FROM $table"
            . (($where) ? " WHERE $where" : '');

        return $this->adapter->query($sql, $bind);
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
        $table  = $this->adapter->quoteIdentifier($table);
        $fields = implode(', ', array_keys($data));
        $placeHolders = implode(', ', array_fill(0, count($data), '?'));
        $sql = "INSERT INTO $table ($fields) VALUES ($placeHolders)";

        $stmt = $this->adapter->query($sql, array_values($data));

        return $stmt->rowCount();
    }

    /**
     * Update data in table.
     *
     * @param string $table
     * @param array $data
     * @param string $where
     * @param array $bind
     * @return int Number of affected rows
     */
    public function update($table, array $data, $where = '', array $bind = array())
    {
        $table  = $this->adapter->quoteIdentifier($table);
        $fields = array();
        foreach (array_keys($data) as $field) {
            $fields[] = "$field = ?";
        }
        $sql = "UPDATE $table SET " . implode(', ', $fields)
            . (($where) ? " WHERE $where" : '');

        $stmt = $this->adapter->query($sql, array_merge(array_values($data), $bind));

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
        $table = $this->adapter->quoteIdentifier($table);
        $sql   = "DELETE FROM $table"
            . (($where) ? " WHERE $where" : '');

        $stmt = $this->adapter->query($sql, $bind);

        return $stmt->rowCount();
    }
}
