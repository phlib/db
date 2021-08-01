<?php

namespace Phlib\Db\Adapter;

trait CrudTrait
{
    /**
     * Select data from table.
     *
     * @param string $table
     * @return \PDOStatement
     */
    public function select($table, array $where = [])
    {
        $table = $this->quote()->identifier($table);
        $sql = "SELECT * FROM {$table}";

        $where = $this->createWhereExpression($where);
        if (!empty($where)) {
            $sql .= " WHERE {$where}";
        }

        return $this->query($sql);
    }

    /**
     * Insert data to table.
     *
     * @param string $table
     * @return int Number of affected rows
     */
    public function insert($table, array $data)
    {
        $table = $this->quote()->identifier($table);
        $fields = implode(', ', array_map([$this->quote(), 'identifier'], array_keys($data)));
        $values = implode(', ', array_map([$this->quote(), 'value'], $data));
        $sql = "INSERT INTO {$table} ({$fields}) VALUES ({$values})";

        $stmt = $this->query($sql);

        return $stmt->rowCount();
    }

    /**
     * Update data in table.
     *
     * @param string $table
     * @return int Number of affected rows
     */
    public function update($table, array $data, array $where = [])
    {
        $table = $this->quote()->identifier($table);
        $fields = [];
        foreach ($data as $field => $value) {
            $identifier = $this->quote()->identifier($field);
            $fields[] = $this->quote()->into("{$identifier} = ?", $value);
        }
        $sql = "UPDATE {$table} SET " . implode(', ', $fields);

        $where = $this->createWhereExpression($where);
        if (!empty($where)) {
            $sql .= " WHERE {$where}";
        }

        $stmt = $this->query($sql);

        return $stmt->rowCount();
    }

    /**
     * Delete from table.
     *
     * @param string $table
     * @return int Number of affected rows
     */
    public function delete($table, array $where = [])
    {
        $table = $this->quote()->identifier($table);
        $sql = "DELETE FROM {$table}";

        $where = $this->createWhereExpression($where);
        if (!empty($where)) {
            $sql .= " WHERE {$where}";
        }

        $stmt = $this->query($sql);

        return $stmt->rowCount();
    }

    /**
     * Insert (on duplicate key update) data in table.
     *
     * @param string $table
     * @return int Number of affected rows
     */
    public function upsert($table, array $data, array $updateFields)
    {
        $table = $this->quote()->identifier($table);
        $fields = implode(', ', array_map([$this->quote(), 'identifier'], array_keys($data)));
        $placeHolders = implode(', ', array_fill(0, count($data), '?'));
        $updateValues = [];
        foreach ($updateFields as $field) {
            $field = $this->quote()->identifier($field);
            $updateValues[] = "{$field} = VALUES({$field})";
        }
        $updates = implode(', ', $updateValues);
        $sql = "INSERT INTO {$table} ({$fields}) VALUES ({$placeHolders}) ON DUPLICATE KEY UPDATE {$updates}";

        $stmt = $this->query($sql, array_values($data));

        return $stmt->rowCount();
    }

    /**
     * Create WHERE expression from given criteria
     *
     * @return string
     */
    private function createWhereExpression(array $where = [])
    {
        $criteria = [];
        foreach ($where as $index => $value) {
            if (is_int($index)) {
                $criteria[] = $value;
            } else {
                $criteria[] = $this->quote()->into($index, $value);
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
     * @throws \PDOException
     * @return \PDOStatement
     */
    abstract public function query($sql, array $bind = []);
}
