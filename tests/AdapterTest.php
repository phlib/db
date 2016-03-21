<?php

namespace Phlib\Db\Tests;

use Phlib\Db\Adapter;

class AdapterTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    protected $pdo;

    /**
     * Quote Character
     * @var string
     */
    protected $qc = '`';

    public function setUp()
    {
        parent::setUp();
        $this->pdo = $this->getMock('\Phlib\Db\Tests\PdoMock');
    }

    public function tearDown()
    {
        $this->pdo = null;
        parent::tearDown();
    }

    /**
     * @covers Phlib\Db\Adapter::setConnection
     */
    public function testSetConnection()
    {
        $dbAdapter = new Adapter();
        $dbAdapter->setConnection($this->pdo);

        $this->assertEquals($this->pdo, $dbAdapter->getConnection());
    }

    /**
     * @covers Phlib\Db\Adapter::setDatabase
     */
    public function testSetDatabaseMakesDbCall()
    {
        $dbname = 'MyDbName';

        $dbAdapter = $this->getMock('\Phlib\Db\Adapter', array('query'));
        $dbAdapter->expects($this->once())
            ->method('query')
            ->with($this->equalTo("USE `$dbname`"));

        $dbAdapter->setConnection($this->pdo);
        $dbAdapter->setDatabase($dbname);
    }

    /**
     * @covers Phlib\Db\Adapter::setDatabase
     */
    public function testSetDatabaseSetsConfig()
    {
        $dbname = 'MyDbName';
        $dbAdapter = $this->getMock('\Phlib\Db\Adapter', array('query'));
        $dbAdapter->setConnection($this->pdo);
        $dbAdapter->setDatabase($dbname);

        $config = $dbAdapter->getConfig();

        $this->assertArrayHasKey('dbname', $config);
        $this->assertSame($dbname, $config['dbname']);
    }

    public function testGetConfigDefaults()
    {
        $defaults = array(
            'charset'  => 'utf8mb4',
            'timezone' => '+0:00'
        );

        $dbAdapter = new Adapter();
        $this->assertEquals($defaults, $dbAdapter->getConfig());
    }

    public function testGetConfigMixed()
    {
        $expected = array(
            'host'     => 'localhost',
            'username' => 'username',
            'password' => 'password',
            'port'     => '3306',
            'charset'  => 'utf8mb4',
            'timezone' => '+0:00'
        );

        $config = array(
            'host'     => 'localhost',
            'username' => 'username',
            'password' => 'password',
            'port'     => '3306'
        );
        $dbAdapter = new Adapter($config);

        $this->assertEquals($expected, $dbAdapter->getConfig());
    }

    /**
     * @covers Phlib\Db\Adapter::getConfig
     */
    public function testGetConfigOverrides()
    {
        $config = array(
            'host'     => 'localhost',
            'username' => 'username',
            'password' => 'password',
            'port'     => '3306',
            'charset'  => 'iso-8859-1',
            'timezone' => '+1:00'
        );
        $dbAdapter = new Adapter($config);

        $this->assertEquals($config, $dbAdapter->getConfig());
    }

    /**
     * @covers Phlib\Db\Adapter::ping
     */
    public function testSuccessfulPing()
    {
        $pdoStatement = $this->getMock('\PDOStatement');
        $pdoStatement
            ->expects($this->any())
            ->method('fetchColumn')
            ->will($this->returnValue(1));
        $this->pdo->expects($this->any())
            ->method('prepare')
            ->will($this->returnValue($pdoStatement));

        $dbAdapter = new Adapter();
        $dbAdapter->setConnection($this->pdo);
        $this->assertTrue($dbAdapter->ping());
    }

    /**
     * @covers Phlib\Db\Adapter::ping
     */
    public function testFailedPing()
    {
        $this->pdo->expects($this->any())
            ->method('prepare')
            ->will($this->throwException(new \Exception));

        $dbAdapter = new Adapter();
        $dbAdapter->setConnection($this->pdo);
        $this->assertFalse($dbAdapter->ping());
    }

    public function testLastInsertIdNoTableName()
    {
        $this->pdo->expects($this->once())
            ->method('lastInsertId')
            ->with($this->equalTo(null));

        $dbAdapter = new Adapter();
        $dbAdapter->setConnection($this->pdo);
        $dbAdapter->lastInsertId();
    }

    public function testLastInsertIdWithTableName()
    {
        $tableName = 'table';
        $this->pdo->expects($this->once())
            ->method('lastInsertId')
            ->with($this->equalTo($tableName));

        $dbAdapter = new Adapter();
        $dbAdapter->setConnection($this->pdo);
        $dbAdapter->lastInsertId($tableName);
    }

    public function testPrepare()
    {
        $pdoStatement = $this->getMock('\PDOStatement');
        $sql = 'SELECT * FROM table';
        $this->pdo->expects($this->once())
            ->method('prepare')
            ->with($this->equalTo($sql))
            ->will($this->returnValue($pdoStatement));

        $dbAdapter = new Adapter();
        $dbAdapter->setConnection($this->pdo);
        $this->assertEquals($pdoStatement, $dbAdapter->prepare($sql));
    }

    public function testExecute()
    {
        $sql = 'dummy sql';

        // Returned stmt will have rowCount called
        $pdoStatement = $this->getMock('\PDOStatement');
        $pdoStatement->expects($this->once())
            ->method('rowCount');

        // Exec should call query with the SQL
        $dbAdapter = $this->getMock('\Phlib\Db\Adapter', array('query'));
        $dbAdapter->expects($this->once())
            ->method('query')
            ->with($sql)
            ->will($this->returnValue($pdoStatement));
        $dbAdapter->setConnection($this->pdo);
        $dbAdapter->execute($sql);
    }

    public function testExecuteBind()
    {
        $sql = 'dummy sql';
        $bind = [1, 2, 3];

        // Returned stmt will have rowCount called
        $pdoStatement = $this->getMock('\PDOStatement');
        $pdoStatement->expects($this->once())
            ->method('rowCount');

        // Exec should call query with the SQL
        $dbAdapter = $this->getMock('\Phlib\Db\Adapter', array('query'));
        $dbAdapter->expects($this->once())
            ->method('query')
            ->with($sql, $bind)
            ->will($this->returnValue($pdoStatement));
        $dbAdapter->setConnection($this->pdo);
        $dbAdapter->execute($sql, $bind);
    }

    public function testQueryNoBind()
    {
        $sql = 'SELECT * FROM table';
        $pdoStatement = $this->getMock('\PDOStatement');
        $pdoStatement->expects($this->once())
            ->method('execute')
            ->with($this->equalTo(array()));
        $this->pdo->expects($this->once())
            ->method('prepare')
            ->with($this->equalTo($sql))
            ->will($this->returnValue($pdoStatement));

        $dbAdapter = new Adapter();
        $dbAdapter->setConnection($this->pdo);
        $this->assertEquals($pdoStatement,$dbAdapter->query($sql));
    }

    public function testQueryWithBind()
    {
        $sql = 'SELECT * FROM table WHERE col1 = ?';
        $bind = array('col1' => 'v1');
        $pdoStatement = $this->getMock('\PDOStatement');
        $pdoStatement->expects($this->once())
            ->method('execute')
            ->with($this->equalTo($bind));
        $this->pdo->expects($this->once())
            ->method('prepare')
            ->with($this->equalTo($sql))
            ->will($this->returnValue($pdoStatement));

        $dbAdapter = new Adapter();
        $dbAdapter->setConnection($this->pdo);
        $this->assertEquals($pdoStatement,$dbAdapter->query($sql, $bind));
    }

    /**
     * @covers Phlib\Db\Adapter::quote
     * @dataProvider quoteDataProvider
     */
    public function testQuote($expected, $value)
    {
        $dbAdapter = $this->createQuoteMethodsAdapter();
        $this->assertEquals($expected, $dbAdapter->quote($value));
    }

    public function quoteDataProvider()
    {
        $qc = $this->qc;
        return array(
            array("{$qc}SomeString{$qc}", 'SomeString'),
            array(123, 123),
            array('NULL', null),
            array('1, 2, 3', array(1, 2, 3)),
            array("{$qc}one{$qc}, {$qc}two{$qc}, {$qc}three{$qc}", array('one', 'two', 'three')),
            array("1, 2, {$qc}Array{$qc}", array(1, 2, array('some', 'other', 'vals'))),
            array('NOW()', new ToStringClass('NOW()'))
        );
    }

    /**
     * @covers Phlib\Db\Adapter::quoteByRef
     * @dataProvider quoteByRefDataProvider
     */
    public function testQuoteByRef($expected, $value)
    {
        $dbAdapter = $this->createQuoteMethodsAdapter();
        $dbAdapter->quoteByRef($value);
        $this->assertEquals($expected, $value);
    }

    public function quoteByRefDataProvider()
    {
        $qc = $this->qc;
        return array(
            array("{$qc}SomeString{$qc}", 'SomeString'),
            array(123, 123),
            array('NULL', null),
            array("{$qc}Array{$qc}", array(1, 2, 3)),
            array("{$qc}Array{$qc}", array('one', 'two', 'three')),
            array("{$qc}Array{$qc}", array(1, 2, array('some', 'other', 'vals'))),
            array('NOW()', new ToStringClass('NOW()'))
        );
    }

    /**
     * @covers Phlib\Db\Adapter::quoteInto
     * @dataProvider quoteIntoDataProvider
     */
    public function testQuoteInto($expected, $text, $value)
    {
        $dbAdapter = $this->createQuoteMethodsAdapter();
        $this->assertEquals($expected, $dbAdapter->quoteInto($text, $value));
    }

    public function quoteIntoDataProvider()
    {
        $qc = $this->qc;
        return array(
            array("field = {$qc}value{$qc}", 'field = ?', 'value'),
            array('field = 123', 'field = ?', 123),
            array('field IS NULL', 'field IS ?', null),
            array('field IN (1, 2, 3)', 'field IN (?)', array(1,2,3)),
            array("field IN ({$qc}one{$qc}, {$qc}two{$qc})", 'field IN (?)', array('one', 'two')),
            array("field IN ({$qc}one{$qc}, {$qc}Array{$qc})", 'field IN (?)', array('one', array('two'))),
            array('field = NOW()', 'field = ?', new ToStringClass('NOW()'))
        );
    }

    /**
     * @dataProvider quoteColumnAsData
     */
    public function testQuoteColumnAs($expected, $ident, $alias, $auto)
    {
        $dbAdapter = new Adapter();
        $result = (!is_null($auto)) ?
            $dbAdapter->quoteColumnAs($ident, $alias, $auto) :
            $dbAdapter->quoteColumnAs($ident, $alias);
        $this->assertEquals($expected, $result);
    }

    public function quoteColumnAsData()
    {
        $qc = "`";
        return array(
            array("{$qc}col1{$qc}", 'col1', null, null),
            array("{$qc}col1{$qc} AS {$qc}alias{$qc}", 'col1', 'alias', null),
            array("{$qc}col1{$qc} AS {$qc}alias{$qc}", 'col1', 'alias', true),
            array("{$qc}table1{$qc}.{$qc}col1{$qc}", array('table1', 'col1'), null, true),
            array("{$qc}table1{$qc}.{$qc}col1{$qc}.{$qc}alias{$qc}", array('table1', 'col1', 'alias'), 'alias', true)
        );
    }

    /**
     * @dataProvider quoteTableAsData
     */
    public function testQuoteTableAs($expected, $ident, $alias, $auto)
    {
        $dbAdapter = new Adapter();
        $result = (!is_null($alias)) ? (!is_null($auto)) ?
            $dbAdapter->quoteTableAs($ident, $alias, $auto) :
            $dbAdapter->quoteTableAs($ident, $alias) :
            $dbAdapter->quoteTableAs($ident);
        $this->assertEquals($expected, $result);
    }

    public function quoteTableAsData()
    {
        $qc = "`";
        return array(
            array("{$qc}table1{$qc}", 'table1', null, null),
            array("{$qc}table1{$qc} AS {$qc}alias{$qc}", 'table1', 'alias', null),
            array("{$qc}table1{$qc} AS {$qc}alias{$qc}", 'table1', 'alias', true),
        );
    }

    /**
     * @covers Phlib\Db\Adapter::insert
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
        $this->pdo->expects($this->any())
            ->method('prepare')
            ->with($this->equalTo($expectedSql))
            ->will($this->returnValue($pdoStatement));

        // Query should be called with the SQL and bind
        $dbAdapter = $this->getMock('\Phlib\Db\Adapter', array('query'));
        $dbAdapter->expects($this->once())
            ->method('query')
            ->with($expectedSql, $bind)
            ->will($this->returnValue($pdoStatement));
        $dbAdapter->setConnection($this->pdo);

        $this->assertEquals(count($data), $dbAdapter->insert($table, $data));
    }

    public function insertDataProvider()
    {
        return array(
            array("INSERT INTO `table` (col1) VALUES (?)",
                'table', array('col1' => 'v1')),
            array("INSERT INTO `table` (col1, col2) VALUES (?, ?)",
                'table', array('col1' => 'v1', 'col2' => 'v2'))
        );
    }

    /**
     * @covers Phlib\Db\Adapter::update
     * @dataProvider updateDataProvider
     */
    public function testUpdate($expectedSql, $table, $data, $where, $bind)
    {
        $bind = (is_null($bind)) ? array() : $bind;
        $executeArgs = array_merge(array_values($data),$bind);

        // Returned stmt will have rowCount called
        $pdoStatement = $this->getMock('\PDOStatement');
        $pdoStatement->expects($this->once())
            ->method('rowCount')
            ->will($this->returnValue(123));

        // Query should be called with the SQL and bind
        $dbAdapter = $this->getMock('\Phlib\Db\Adapter', array('query'));
        $dbAdapter->expects($this->once())
            ->method('query')
            ->with($expectedSql, $executeArgs)
            ->will($this->returnValue($pdoStatement));
        $dbAdapter->setConnection($this->pdo);

        $result = (!is_null($where)) ? (!is_null($bind)) ?
            $dbAdapter->update($table, $data, $where, $bind) :
            $dbAdapter->update($table, $data, $where) :
            $dbAdapter->update($table, $data);

        $this->assertEquals(123, $result);
    }

    public function updateDataProvider()
    {
        $qc = $this->qc;
        return array(
            array("UPDATE `table` SET col1 = ?",
                'table', array('col1' => 'v1'), null, null),
            array("UPDATE `table` SET col1 = ?, col2 = ?",
                'table', array('col1' => 'v1', 'col2' => 'v2'), null, null),
            array("UPDATE `table` SET col1 = ?, col2 = ? WHERE col3 = {$qc}v3{$qc}",
                'table', array('col1' => 'v1', 'col2' => 'v2'), "col3 = {$qc}v3{$qc}", null),
            array("UPDATE `table` SET col1 = ? WHERE col3 = {$qc}v3{$qc} AND col4 = ?",
                'table', array('col1' => 'v1'), "col3 = {$qc}v3{$qc} AND col4 = ?", array('v4'))
        );
    }

    /**
     * @covers Phlib\Db\Adapter::delete
     * @dataProvider deleteDataProvider
     */
    public function testDelete($expectedSql, $table, $where, $bind)
    {
        $executeArgs = (is_null($bind)) ? array() : $bind;

        // Returned stmt will have rowCount called
        $pdoStatement = $this->getMock('\PDOStatement');
        $pdoStatement->expects($this->once())
            ->method('rowCount')
            ->will($this->returnValue(123));

        // Query should be called with the SQL and bind
        $dbAdapter = $this->getMock('\Phlib\Db\Adapter', array('query'));
        $dbAdapter->expects($this->once())
            ->method('query')
            ->with($expectedSql, $executeArgs)
            ->will($this->returnValue($pdoStatement));
        $dbAdapter->setConnection($this->pdo);

        $result = (!is_null($where)) ? (!is_null($bind)) ?
            $dbAdapter->delete($table, $where, $bind) :
            $dbAdapter->delete($table, $where) :
            $dbAdapter->delete($table);

        $this->assertEquals(123, $result);
    }

    public function deleteDataProvider()
    {
        $qc = $this->qc;
        return array(
            array("DELETE FROM `table`",
                'table', null, null),
            array("DELETE FROM `table` WHERE col1 = {$qc}v1{$qc}",
                'table', "col1 = {$qc}v1{$qc}", null),
            array("DELETE FROM `table` WHERE col1 = ?",
                'table', "col1 = ?", array("$qc}v1{$qc}"))
        );
    }

    /**
     * @dataProvider quoteIdentifierData
     */
    public function testQuoteIdentifier($expected, $ident, $auto)
    {
        $dbAdapter = new Adapter();
        $result = (!is_null($auto)) ?
            $dbAdapter->quoteIdentifier($ident, $auto) :
            $dbAdapter->quoteIdentifier($ident);
        $this->assertEquals($expected, $result);
    }

    public function quoteIdentifierData()
    {
        $qc = "`";
        return array(
            array("{$qc}col1{$qc}", 'col1', null),
            array("{$qc}col1{$qc}", 'col1', true),
            array("NOW()", new ToStringClass('NOW()'), true),
            array("{$qc}col1{$qc}.NOW()", array('col1', new ToStringClass('NOW()')), true),
            array("{$qc}table1{$qc}.{$qc}*{$qc}", 'table1.*', true)
        );
    }

    public function testSetBufferedConnectionToUnbuffered()
    {
        $this->pdo->expects($this->once())
            ->method('setAttribute')
            ->with(
                $this->equalTo(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY),
                $this->equalTo(false)
            );

        $adapter = new Adapter();
        $adapter->setConnection($this->pdo);
        $adapter->disableBuffering();
    }

    public function testIsBufferedConnection()
    {
        $this->pdo->expects($this->once())
            ->method('getAttribute')
            ->with($this->equalTo(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY))
            ->will($this->returnValue(true));

        $adapter = new Adapter();
        $adapter->setConnection($this->pdo);
        $this->assertTrue($adapter->isBuffered());
    }

    protected function createQuoteMethodsAdapter()
    {
        $qc = $this->qc;
        $this->pdo->expects($this->any())
            ->method('quote')
            ->will($this->returnCallback(function($arg) use ($qc) {
                return "{$qc}{$arg}{$qc}";
            }));

        $dbAdapter = new Adapter();
        $dbAdapter->setConnection($this->pdo);

        return $dbAdapter;
    }
}
