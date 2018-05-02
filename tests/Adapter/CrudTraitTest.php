<?php

namespace Phlib\Db\Tests\Adapter;

use Phlib\Db\Adapter\CrudTrait;
use Phlib\Db\Adapter\QuoteHandler;
use Phlib\Db\SqlFragment;

class CrudTraitTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var CrudTrait|\PHPUnit_Framework_MockObject_MockObject
     */
    private $crud;

    public function setUp()
    {
        parent::setUp();

        $this->crud = $this->getMockBuilder(CrudTrait::class)
            ->setMethods(['query', 'quote'])
            ->getMockForTrait();

        $quoteHandler = new QuoteHandler(function ($value) {
            return "'{$value}'";
        });
        $this->crud->method('quote')
            ->willReturn($quoteHandler);
    }

    public function tearDown()
    {
        $this->crud = null;
        parent::tearDown();
    }

    /**
     * @param string $expectedSql
     * @param string $table
     * @param array|string $where
     * @param array $bind
     * @dataProvider selectDataProvider
     */
    public function testSelect($expectedSql, $table, $where, array $bind)
    {
        $pdoStatement = $this->createMock(\PDOStatement::class);

        $this->crud->expects($this->once())
            ->method('query')
            ->with($expectedSql, $bind)
            ->will($this->returnValue($pdoStatement));

        (!empty($where)) ? (!empty($bind)) ?
            $this->crud->select($table, $where, $bind) :
            $this->crud->select($table, $where) :
            $this->crud->select($table);
    }

    public function selectDataProvider()
    {
        return [
            ["SELECT * FROM `my_table`", 'my_table', [], []],
            // Deprecated $where param as string
            ["SELECT * FROM `my_table` WHERE id = 1", 'my_table', 'id = 1', []],
            // Deprecated $where param as string with bind
            ["SELECT * FROM `my_table` WHERE id = ?", 'my_table', 'id = ?', [1]],
            [
                "SELECT * FROM `table` WHERE col1 = 'v1' AND col2 = 'v2' AND col3 IS NULL",
                'table',
                ['col1 = ?' => 'v1', 'col2 = ?' => 'v2', 'col3 IS NULL'],
                []
            ],
            // Correct quote behaviour for number and object
            [
                "SELECT * FROM `table` WHERE col1 = 123 AND col2 = col3",
                'table',
                ['col1 = ?' => 123, 'col2 = ?' => new SqlFragment('col3')],
                []
            ]
        ];
    }

    public function testSelectReturnsStatement()
    {
        $pdoStatement = $this->createMock(\PDOStatement::class);
        $this->crud->expects($this->any())
            ->method('query')
            ->will($this->returnValue($pdoStatement));

        $this->assertSame($pdoStatement, $this->crud->select('my_table'));
    }

    /**
     * @param string $expectedSql
     * @param string $table
     * @param array $data
     * @dataProvider insertDataProvider
     */
    public function testInsert($expectedSql, $table, array $data)
    {
        // Returned stmt will have rowCount called
        $pdoStatement = $this->createMock(\PDOStatement::class);
        $pdoStatement->expects($this->once())
            ->method('rowCount')
            ->will($this->returnValue(count($data)));

        $this->crud->expects($this->any())
            ->method('query')
            ->with($expectedSql, [])
            ->will($this->returnValue($pdoStatement));

        $this->crud->insert($table, $data);
    }

    public function insertDataProvider()
    {
        return [
            ["INSERT INTO `table` (col1) VALUES ('v1')", 'table', ['col1' => 'v1']],
            ["INSERT INTO `table` (col1, col2) VALUES ('v1', 'v2')", 'table', ['col1' => 'v1', 'col2' => 'v2']],
            // Number should not be quoted
            ["INSERT INTO `table` (col1) VALUES (123)", 'table', ['col1' => 123]],
            // Object should be handled
            ["INSERT INTO `table` (col1) VALUES (col2)", 'table', ['col1' => new SqlFragment('col2')]],
        ];
    }

    /**
     * @param string $expectedSql
     * @param string $table
     * @param array $data
     * @param array|string $where
     * @param array $bind
     * @dataProvider updateDataProvider
     */
    public function testUpdate($expectedSql, $table, $data, $where, array $bind)
    {
        $bind = (is_null($bind)) ? [] : $bind;
        $executeArgs = $bind;

        // Returned stmt will have rowCount called
        $pdoStatement = $this->createMock(\PDOStatement::class);
        $pdoStatement->expects($this->once())
            ->method('rowCount');

        // Query should be called with the SQL and bind
        $this->crud->expects($this->once())
            ->method('query')
            ->with($expectedSql, $executeArgs)
            ->will($this->returnValue($pdoStatement));

        $result = (!empty($where)) ? (!empty($bind)) ?
            $this->crud->update($table, $data, $where, $bind) :
            $this->crud->update($table, $data, $where) :
            $this->crud->update($table, $data);
    }

    public function updateDataProvider()
    {
        return [
            ["UPDATE `table` SET col1 = 'v1'", 'table', ['col1' => 'v1'], [], []],
            ["UPDATE `table` SET col1 = 'v1', col2 = 'v2'", 'table', ['col1' => 'v1', 'col2' => 'v2'], [], []],
            // Deprecated $where param as string
            [
                "UPDATE `table` SET col1 = 'v1', col2 = 'v2' WHERE col3 = 'v3'",
                'table',
                ['col1' => 'v1', 'col2' => 'v2'],
                "col3 = 'v3'",
                []
            ],
            // Deprecated $where param as string with bind
            [
                "UPDATE `table` SET col1 = 'v1' WHERE col3 = 'v3' AND col4 = ?",
                'table',
                ['col1' => 'v1'],
                "col3 = 'v3' AND col4 = ?",
                ['v4']
            ],
            [
                "UPDATE `table` SET col1 = 'v1' WHERE col3 = 'v3' AND col4 = 'v4' AND col5 IS NULL",
                'table',
                ['col1' => 'v1'],
                ['col3 = ?' => 'v3', 'col4 = ?' => 'v4', 'col5 IS NULL'],
                []
            ],
            // Correct quote behaviour for number and object
            [
                "UPDATE `table` SET col1 = 'v1' WHERE col3 = 123 AND col4 = col2",
                'table',
                ['col1' => 'v1'],
                ['col3 = ?' => 123, 'col4 = ?' => new SqlFragment('col2')],
                []
            ]
        ];
    }

    /**
     * @param string $expectedSql
     * @param string $table
     * @param array|string $where
     * @param array $bind
     * @dataProvider deleteDataProvider
     */
    public function testDelete($expectedSql, $table, $where, array $bind)
    {
        $executeArgs = (is_null($bind)) ? [] : $bind;

        // Returned stmt will have rowCount called
        $pdoStatement = $this->createMock(\PDOStatement::class);
        $pdoStatement->expects($this->once())
            ->method('rowCount');

        // Query should be called with the SQL and bind
        $this->crud->expects($this->once())
            ->method('query')
            ->with($expectedSql, $executeArgs)
            ->will($this->returnValue($pdoStatement));

        (!empty($where)) ? (!empty($bind)) ?
            $this->crud->delete($table, $where, $bind) :
            $this->crud->delete($table, $where) :
            $this->crud->delete($table);
    }

    public function deleteDataProvider()
    {
        return [
            ["DELETE FROM `table`", 'table', [], []],
            // Deprecated $where param as string
            ["DELETE FROM `table` WHERE col1 = 'v1'", 'table', "col1 = 'v1'", []],
            // Deprecated $where param as string with bind
            ["DELETE FROM `table` WHERE col1 = ?", 'table', "col1 = ?", ["'v1'"]],
            [
                "DELETE FROM `table` WHERE col1 = 'v1' AND col2 = 'v2' AND col3 IS NULL",
                'table',
                ['col1 = ?' => 'v1', 'col2 = ?' => 'v2', 'col3 IS NULL'],
                []
            ],
            // Correct quote behaviour for number and object
            [
                "DELETE FROM `table` WHERE col1 = 123 AND col2 = col3",
                'table',
                ['col1 = ?' => 123, 'col2 = ?' => new SqlFragment('col3')],
                []
            ]
        ];
    }

    /**
     * @dataProvider upsertDataProvider
     */
    public function testUpsert($expectedSql, $table, array $data, array $updateFields)
    {
        // Returned stmt will have rowCount called
        $pdoStatement = $this->createMock(\PDOStatement::class);
        $pdoStatement->expects($this->once())
            ->method('rowCount')
            ->willReturn(1);

        $bind = array_values($data);
        // Query should be called with the SQL and bind
        $this->crud->expects($this->once())
            ->method('query')
            ->with($expectedSql, $bind)
            ->willReturn($pdoStatement);

        $this->assertEquals(1, $this->crud->upsert($table, $data, $updateFields));
    }

    public function upsertDataProvider()
    {
        return [
            ["INSERT INTO `table` (`col1`) VALUES (?) ON DUPLICATE KEY UPDATE `col1` = VALUES(`col1`)",
                'table', ['col1' => 'v1'], ['col1']],
            ["INSERT INTO `table` (`col1`, `col2`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `col1` = VALUES(`col1`)",
                'table', ['col1' => 'v1', 'col2' => 'v2'], ['col1']],
            ["INSERT INTO `table` (`col1`, `col2`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `col1` = VALUES(`col1`), `col2` = VALUES(`col2`)",
                'table', ['col1' => 'v1', 'col2' => 'v2'], ['col1', 'col2']],
        ];
    }
}
