<?php

namespace Phlib\Db\Tests;

use Phlib\Db\Adapter;
use Phlib\Db\BulkInsert;

class BulkInsertTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \Phlib\Db\Adapter|PHPUnit_Framework_MockObject_MockObject
     */
    protected $adapter;

    protected function setUp()
    {
        $this->adapter = $this->getMock(Adapter::class);
        parent::setUp();
    }

    public function tearDown()
    {
        parent::tearDown();
        $this->adapter = null;
    }

    /**
     * @dataProvider fetchSqlIgnoreUpdateDataProvider
     * @param bool $ignore
     * @param bool $update
     */
    public function testFetchSqlIgnoreUpdate($ignore, $update)
    {
        $table = 'test_table';
        $insertFields = array('field');
        $updateFields = array();
        if ($update) {
            $updateFields = array('field');
        }
        $inserter = new BulkInsert($this->adapter, $table, $insertFields, $updateFields);

        if ($ignore) {
            $inserter->insertIgnoreEnabled();
        } else {
            $inserter->insertIgnoreDisabled();
        }

        $inserter->add(array('value'));
        $actual = $inserter->fetchSql();

        $needle = 'INSERT IGNORE INTO';
        if ($ignore && !$update) {
            $this->assertContains($needle, $actual);
        } else {
            $this->assertNotContains($needle, $actual);
        }

        $needle = 'ON DUPLICATE KEY UPDATE';
        if ($update) {
            $this->assertContains($needle, $actual);
        } else {
            $this->assertNotContains($needle, $actual);
        }
    }

    public function fetchSqlIgnoreUpdateDataProvider()
    {
        return array(
            array(true, true),
            array(true, false),
            array(false, true),
            array(false, false)
        );
    }

    public function testAddCallsWriteWhenExceedsBatchSize()
    {
        $inserter = $this->getMockBuilder(BulkInsert::class)
            ->setConstructorArgs([$this->adapter, 'table_name', ['field1'], [], ['batchSize' => 1]])
            ->setMethods(['write'])
            ->getMock();

        $inserter->expects($this->once())
            ->method('write');

        $inserter->add(['field1' => 'foo']);
    }

//    public function testWrite()
//    {
//
//    }
}
