<?php

namespace Phlib\Db\Adapter;

trait CrudTrait
{
    /**
     * Select data from table.
     *
     * @param string $table
     * @param array|string $where Deprecated usage of parameter as string
     * @param array $bind Deprecated in favour of $where as array
     * @return \PDOStatement
     */
    public function select($table, $where = [], array $bind = [])
    {
        $table = $this->quote()->identifier($table);
        $sql   = "SELECT * FROM $table";
        if (is_array($where)) {
            $where = $this->createWhereExpression($where);
        }
        $sql .= (($where) ? " WHERE {$where}" : '');

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
        $values = implode(', ', array_map([$this->quote(), 'value'], $data));
        $sql = "INSERT INTO $table ($fields) VALUES ($values)";

        $stmt = $this->query($sql);

        return $stmt->rowCount();
    }

    /**
     * Update data in table.
     *
     * @param string $table
     * @param array $data
     * @param array|string $where Deprecated usage of parameter as string
     * @param array $bind Deprecated in favour of $where as array
     * @return int Number of affected rows
     */
    public function update($table, array $data, $where = [], array $bind = [])
    {
        $table  = $this->quote()->identifier($table);
        $fields = [];
        foreach ($data as $field => $value) {
            $fields[] = $this->quote()->into("{$field} = ?", $value);
        }
        $sql = "UPDATE $table SET " . implode(', ', $fields);
        if (is_array($where)) {
            $where = $this->createWhereExpression($where);
        }
        $sql .= (($where) ? " WHERE {$where}" : '');

        $stmt = $this->query($sql, $bind);

        return $stmt->rowCount();
    }

    /**
     * Delete from table.
     *
     * @param string $table
     * @param array|string $where Deprecated usage of parameter as string
     * @param array $bind Deprecated in favour of $where as array
     * @return int Number of affected rows
     */
    public function delete($table, $where = [], array $bind = [])
    {
        $table = $this->quote()->identifier($table);
        $sql   = "DELETE FROM $table";
        if (is_array($where)) {
            $where = $this->createWhereExpression($where);
        }
        $sql .= (($where) ? " WHERE {$where}" : '');

        $stmt = $this->query($sql, $bind);

        return $stmt->rowCount();
    }

    /**
     * Create WHERE expression from given criteria
     *
     * @param array $where
     * @return string
     */
    private function createWhereExpression(array $where = [])
    {
        $criteria = [];
        foreach ($where as $str => $value) {
            if (is_int($str)) {
                $criteria[] = $value;
            } else {
                $criteria[] = $this->quote()->into($str, $value);
            }
        }
        return implode(' AND ', $criteria);
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
