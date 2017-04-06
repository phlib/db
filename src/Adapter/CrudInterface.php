<?php

namespace Phlib\Db\Adapter;

interface CrudInterface
{
    /**
     * Select data from table.
     *
     * @param string $table
     * @param string $where
     * @param array $bind
     * @return \PDOStatement
     */
    public function select($table, $where = '', array $bind = []);

    /**
     * Insert data to table.
     *
     * @param string $table
     * @param array $data
     * @return int Number of affected rows
     */
    public function insert($table, array $data);

    /**
     * Update data in table.
     *
     * @param string $table
     * @param array $data
     * @param string $where
     * @param array $bind
     * @return int Number of affected rows
     */
    public function update($table, array $data, $where = '', array $bind = []);

    /**
     * Delete from table.
     *
     * @param string $table
     * @param string $where
     * @param array $bind
     * @return int Number of affected rows
     */
    public function delete($table, $where = '', array $bind = []);
}
