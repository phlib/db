<?php

declare(strict_types=1);

namespace Phlib\Db\Adapter;

trait CrudTrait
{
    public function select(string $table, array $where = []): \PDOStatement
    {
        $table = $this->quote()->identifier($table);
        $sql = "SELECT * FROM {$table}";

        $where = $this->createWhereExpression($where);
        if (!empty($where)) {
            $sql .= " WHERE {$where}";
        }

        return $this->query($sql);
    }

    public function insert(string $table, array $data): int
    {
        return $this->insertReplace('INSERT', $table, $data);
    }

    public function replace(string $table, array $data): int
    {
        return $this->insertReplace('REPLACE', $table, $data);
    }

    private function insertReplace(string $statementType, string $table, array $data): int
    {
        $table = $this->quote()->identifier($table);
        $fields = implode(', ', array_map([$this->quote(), 'identifier'], array_keys($data)));
        $values = implode(', ', array_map([$this->quote(), 'value'], $data));
        $sql = "{$statementType} INTO {$table} ({$fields}) VALUES ({$values})";

        $stmt = $this->query($sql);

        return $stmt->rowCount();
    }

    /**
     * @return int Number of affected rows
     */
    public function update(string $table, array $data, array $where = []): int
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
     * @return int Number of affected rows
     */
    public function delete(string $table, array $where = []): int
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
     * @return int Number of affected rows
     */
    public function upsert(string $table, array $data, array $updateFields): int
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

    private function createWhereExpression(array $where = []): string
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

    abstract public function quote(): QuoteHandler;

    abstract public function query(string $sql, array $bind = []): \PDOStatement;
}
