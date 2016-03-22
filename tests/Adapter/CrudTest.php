<?php

namespace Phlib\Db\Tests\Adapter;

use Phlib\Db\Adapter;
use Phlib\Db\Adapter\Crud;

class CrudTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Adapter|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $adapter;

    /**
     * @var Crud
     */
    protected $crud;

    public function setUp()
    {
        parent::setUp();
        $this->adapter = $this->getMock('\Phlib\Db\Adapter', ['query']);
        $this->crud    = new Crud($this->adapter);
    }

    public function tearDown()
    {
        $this->crud    = null;
        $this->adapter = null;
        parent::tearDown();
    }

    /**
     * @dataProvider insertDataProvider
     */
    public function testInsert($expectedSql, $table, $data)
    {
        // Returned stmt will have rowCount called
        $pdoStatement = $this->getMock('\PDOStatement');
        $pdoStatement->expects($this->once())
            ->method('rowCount')
            ->will($this->returnValue(count($data)));

        $bind = array_values($data);
        $this->adapter->expects($this->any())
            ->method('query')
            ->with($expectedSql, $bind)
            ->will($this->returnValue($pdoStatement));

        $this->crud->insert($table, $data);
    }

    public function insertDataProvider()
    {
        return [
            ["INSERT INTO `table` (col1) VALUES (?)", 'table', ['col1' => 'v1']],
            ["INSERT INTO `table` (col1, col2) VALUES (?, ?)", 'table', ['col1' => 'v1', 'col2' => 'v2']]
        ];
    }

    /**
     * @dataProvider updateDataProvider
     */
    public function testUpdate($expectedSql, $table, $data, $where, $bind)
    {
        $bind = (is_null($bind)) ? [] : $bind;
        $executeArgs = array_merge(array_values($data),$bind);

        // Returned stmt will have rowCount called
        $pdoStatement = $this->getMock('\PDOStatement');
        $pdoStatement->expects($this->once())
            ->method('rowCount');

        // Query should be called with the SQL and bind
        $this->adapter->expects($this->once())
            ->method('query')
            ->with($expectedSql, $executeArgs)
            ->will($this->returnValue($pdoStatement));

        $result = (!is_null($where)) ? (!is_null($bind)) ?
            $this->crud->update($table, $data, $where, $bind) :
            $this->crud->update($table, $data, $where) :
            $this->crud->update($table, $data);
    }

    public function updateDataProvider()
    {
        return [
            ["UPDATE `table` SET col1 = ?", 'table', ['col1' => 'v1'], null, null],
            ["UPDATE `table` SET col1 = ?, col2 = ?", 'table', ['col1' => 'v1', 'col2' => 'v2'], null, null],
            [
                "UPDATE `table` SET col1 = ?, col2 = ? WHERE col3 = `v3`",
                'table',
                ['col1' => 'v1', 'col2' => 'v2'], "col3 = `v3`",
                null
            ],
            [
                "UPDATE `table` SET col1 = ? WHERE col3 = `v3` AND col4 = ?",
                'table',
                ['col1' => 'v1'],
                "col3 = `v3` AND col4 = ?",
                ['v4']
            ]
        ];
    }

    /**
     * @dataProvider deleteDataProvider
     */
    public function testDelete($expectedSql, $table, $where, $bind)
    {
        $executeArgs = (is_null($bind)) ? [] : $bind;

        // Returned stmt will have rowCount called
        $pdoStatement = $this->getMock('\PDOStatement');
        $pdoStatement->expects($this->once())
            ->method('rowCount');

        // Query should be called with the SQL and bind
        $this->adapter->expects($this->once())
            ->method('query')
            ->with($expectedSql, $executeArgs)
            ->will($this->returnValue($pdoStatement));

        $result = (!is_null($where)) ? (!is_null($bind)) ?
            $this->crud->delete($table, $where, $bind) :
            $this->crud->delete($table, $where) :
            $this->crud->delete($table);
    }

    public function deleteDataProvider()
    {
        return [
            ["DELETE FROM `table`", 'table', null, null],
            ["DELETE FROM `table` WHERE col1 = `v1`", 'table', "col1 = `v1`", null],
            ["DELETE FROM `table` WHERE col1 = ?", 'table', "col1 = ?", ["`v1`"]]
        ];
    }
}
