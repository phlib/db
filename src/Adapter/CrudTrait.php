<?php

namespace Phlib\Db\Adapter;

trait CrudTrait
{
    /**
     * Select data from table.
     *
     * @param string $table
     * @param string $where
     * @param array $bind
     * @return \PDOStatement
     */
    public function select($table, $where = '', array $bind = [])
    {
        $table = $this->quote()->identifier($table);
        $sql   = "SELECT * FROM $table"
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
        $table  = $this->quote()->identifier($table);
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
     * @return int Number of affected rows
     */
    public function update($table, array $data, $where = '', array $bind = [])
    {
        $table  = $this->quote()->identifier($table);
        $fields = [];
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
    public function delete($table, $where = '', array $bind = [])
    {
        $table = $this->quote()->identifier($table);
        $sql   = "DELETE FROM $table"
            . (($where) ? " WHERE $where" : '');

        $stmt = $this->query($sql, $bind);

        return $stmt->rowCount();
    }

    /**
     * @return QuoteHandler
     */
    abstract public function quote();

    /**
     * @param string $sql
     * @param array $bind
     * @throws \PDOException
     * @return \PDOStatement
     */
    abstract public function query($sql, array $bind = []);
}
