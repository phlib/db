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
}
