<?php

namespace Phlib\Db\Tests;

use Phlib\Db\Adapter;

/**
 * This class is here for the code reflection when mocking as the following methods
 * are not known about otherwise.
 *
 * Class AdapterMock
 * @package Phlib\Db\Tests
 */
class AdapterMock extends Adapter
{
    public function select($table, $where = '', array $bind = array())
    {
    }

    public function insert($table, array $data)
    {
    }

    public function update($table, array $data, $where = '', array $bind = array())
    {
    }

    public function delete($table, $where = '', array $bind = array())
    {
    }

    public function quote($value, $type = null)
    {
    }

    public function quoteInto($text, $value, $type = null)
    {
    }

    public function quoteColumnAs($ident, $alias, $auto = false)
    {
    }

    public function quoteTableAs($ident, $alias = null, $auto = false)
    {
    }

    public function quoteIdentifier($ident, $auto = false)
    {
    }
}
