<?php

namespace Phlib\Db\Tests\Adapter;

use Phlib\Db\Adapter\CrudTrait;
use Phlib\Db\Adapter\QuoteHandler;
use Phlib\Db\SqlFragment;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class CrudTraitTest extends TestCase
{
    /**
     * @var CrudTrait|MockObject
     */
    private $crud;

    protected function setUp()
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

    /**
     * @param string $expectedSql
     * @param string $table
     * @param array $where
     * @dataProvider selectDataProvider
     */
    public function testSelect($expectedSql, $table, array $where)
    {
        $pdoStatement = $this->createMock(\PDOStatement::class);

        $this->crud->expects(static::once())
            ->method('query')
            ->with($expectedSql, [])
            ->willReturn($pdoStatement);

        (!empty($where)) ?
            $this->crud->select($table, $where) :
            $this->crud->select($table);
    }

    public function selectDataProvider()
    {
        return [
            ["SELECT * FROM `my_table`", 'my_table', []],
            [
                "SELECT * FROM `table` WHERE col1 = 'v1' AND col2 = 'v2' AND col3 IS NULL",
                'table',
                ['col1 = ?' => 'v1', 'col2 = ?' => 'v2', 'col3 IS NULL'],
            ],
            // Correct quote behaviour for number and object
            [
                "SELECT * FROM `table` WHERE col1 = 123 AND col2 = col3",
                'table',
                ['col1 = ?' => 123, 'col2 = ?' => new SqlFragment('col3')],
            ]
        ];
    }

    public function testSelectReturnsStatement()
    {
        $pdoStatement = $this->createMock(\PDOStatement::class);
        $this->crud->method('query')
            ->willReturn($pdoStatement);

        static::assertSame($pdoStatement, $this->crud->select('my_table'));
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
        $pdoStatement->expects(static::once())
            ->method('rowCount')
            ->willReturn(count($data));

        $this->crud->method('query')
            ->with($expectedSql, [])
            ->willReturn($pdoStatement);

        $this->crud->insert($table, $data);
    }

    public function insertDataProvider()
    {
        return [
            ["INSERT INTO `table` (`col1`) VALUES ('v1')", 'table', ['col1' => 'v1']],
            ["INSERT INTO `table` (`col1`, `col2`) VALUES ('v1', 'v2')", 'table', ['col1' => 'v1', 'col2' => 'v2']],
            // Number should not be quoted
            ["INSERT INTO `table` (`col1`) VALUES (123)", 'table', ['col1' => 123]],
            // Object should be handled
            ["INSERT INTO `table` (`col1`) VALUES (col2)", 'table', ['col1' => new SqlFragment('col2')]],
        ];
    }

    /**
     * @param string $expectedSql
     * @param string $table
     * @param array $data
     * @param array $where
     * @dataProvider updateDataProvider
     */
    public function testUpdate($expectedSql, $table, $data, array $where)
    {
        // Returned stmt will have rowCount called
        $pdoStatement = $this->createMock(\PDOStatement::class);
        $pdoStatement->expects(static::once())
            ->method('rowCount');

        // Query should be called with the SQL and bind
        $this->crud->expects(static::once())
            ->method('query')
            ->with($expectedSql, [])
            ->willReturn($pdoStatement);

        (!empty($where)) ?
            $this->crud->update($table, $data, $where) :
            $this->crud->update($table, $data);
    }

    public function updateDataProvider()
    {
        return [
            ["UPDATE `table` SET `col1` = 'v1'", 'table', ['col1' => 'v1'], []],
            ["UPDATE `table` SET `col1` = 'v1', `col2` = 'v2'", 'table', ['col1' => 'v1', 'col2' => 'v2'], []],
            [
                "UPDATE `table` SET `col1` = 'v1' WHERE col3 = 'v3' AND col4 = 'v4' AND col5 IS NULL",
                'table',
                ['col1' => 'v1'],
                ['col3 = ?' => 'v3', 'col4 = ?' => 'v4', 'col5 IS NULL'],
            ],
            // Correct quote behaviour for number and object
            [
                "UPDATE `table` SET `col1` = 'v1' WHERE col3 = 123 AND col4 = col2",
                'table',
                ['col1' => 'v1'],
                ['col3 = ?' => 123, 'col4 = ?' => new SqlFragment('col2')],
            ]
        ];
    }

    /**
     * @param string $expectedSql
     * @param string $table
     * @param array $where
     * @dataProvider deleteDataProvider
     */
    public function testDelete($expectedSql, $table, array $where)
    {
        // Returned stmt will have rowCount called
        $pdoStatement = $this->createMock(\PDOStatement::class);
        $pdoStatement->expects(static::once())
            ->method('rowCount');

        // Query should be called with the SQL and bind
        $this->crud->expects(static::once())
            ->method('query')
            ->with($expectedSql, [])
            ->willReturn($pdoStatement);

        (!empty($where)) ?
            $this->crud->delete($table, $where) :
            $this->crud->delete($table);
    }

    public function deleteDataProvider()
    {
        return [
            ["DELETE FROM `table`", 'table', []],
            [
                "DELETE FROM `table` WHERE col1 = 'v1' AND col2 = 'v2' AND col3 IS NULL",
                'table',
                ['col1 = ?' => 'v1', 'col2 = ?' => 'v2', 'col3 IS NULL'],
            ],
            // Correct quote behaviour for number and object
            [
                "DELETE FROM `table` WHERE col1 = 123 AND col2 = col3",
                'table',
                ['col1 = ?' => 123, 'col2 = ?' => new SqlFragment('col3')],
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
        $pdoStatement->expects(static::once())
            ->method('rowCount')
            ->willReturn(1);

        $bind = array_values($data);
        // Query should be called with the SQL and bind
        $this->crud->expects(static::once())
            ->method('query')
            ->with($expectedSql, $bind)
            ->willReturn($pdoStatement);

        static::assertEquals(1, $this->crud->upsert($table, $data, $updateFields));
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
