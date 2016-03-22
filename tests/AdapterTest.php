<?php

namespace Phlib\Db\Tests;

use Phlib\Db\Adapter;
use Phlib\Db\Exception\UnknownDatabaseException;

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

    public function testQuoteHandlerIsSetupCorrectly()
    {
        $string = 'foo';
        $this->pdo->expects($this->once())
            ->method('quote')
            ->with($string);

        $adapter = new Adapter();
        $adapter->setConnection($this->pdo);

        $adapter->getQuoteHandler()->quote($string);
    }

    public function testGetDefaultQuoteHandler()
    {
        $adapter = new Adapter();
        $adapter->setConnection($this->pdo);
        $this->assertInstanceOf(Adapter\QuoteHandler::class, $adapter->getQuoteHandler());
    }

    public function testSetGetQuoteHandler()
    {
        $handler = $this->getMockBuilder(Adapter\QuoteHandler::class)
            ->disableOriginalConstructor()
            ->getMock();
        $adapter = new Adapter();
        $adapter->setConnection($this->pdo);
        $adapter->setQuoteHandler($handler);
        $this->assertSame($handler, $adapter->getQuoteHandler());
    }

    public function testQuoteHandlerForwardingMethods()
    {
        $handler = $this->getMockBuilder(Adapter\QuoteHandler::class)
            ->disableOriginalConstructor()
            ->getMock();
        $handler->expects($this->once())
            ->method('quote');

        $adapter = new Adapter();
        $adapter->setConnection($this->pdo);
        $adapter->setQuoteHandler($handler);
        $adapter->quote('foo');
    }

    public function testCrudHelperForwardingMethods()
    {
        $helper = $this->getMockBuilder(Adapter\Crud::class)
            ->disableOriginalConstructor()
            ->getMock();
        $helper->expects($this->once())
            ->method('select');

        $adapter = new Adapter();
        $adapter->setConnection($this->pdo);
        $adapter->setCrudHelper($helper);
        $adapter->select('foo');
    }

    /**
     * @covers Phlib\Db\Adapter::setConnection
     */
    public function testSetConnection()
    {
        $adapter = new Adapter();
        $adapter->setConnection($this->pdo);

        $this->assertEquals($this->pdo, $adapter->getConnection());
    }

    /**
     * @covers Phlib\Db\Adapter::setDatabase
     */
    public function testSetDatabaseMakesDbCall()
    {
        $dbname = 'MyDbName';

        $adapter = $this->getMock('\Phlib\Db\Adapter', array('query'));
        $adapter->expects($this->once())
            ->method('query')
            ->with($this->equalTo("USE `$dbname`"));

        $adapter->setConnection($this->pdo);
        $adapter->setDatabase($dbname);
    }

    /**
     * @covers Phlib\Db\Adapter::setDatabase
     */
    public function testSetDatabaseSetsConfig()
    {
        $dbname = 'MyDbName';
        $adapter = $this->getMock('\Phlib\Db\Adapter', array('query'));
        $adapter->setConnection($this->pdo);
        $adapter->setDatabase($dbname);

        $config = $adapter->getConfig();

        $this->assertArrayHasKey('dbname', $config);
        $this->assertSame($dbname, $config['dbname']);
    }

    /**
     * @expectedException \Phlib\Db\Exception\UnknownDatabaseException
     */
    public function testSetDatabaseWhenItsUnknown()
    {
        $database  = 'foobar';
        $exception = new \PDOException("SQLSTATE[42000] [1049] Unknown database '$database'.", 42000);
        $statement = $this->getMock(\PDOStatement::class);
        $statement->expects($this->any())
            ->method('execute')
            ->will($this->throwException($exception));
        $this->pdo->expects($this->any())
            ->method('prepare')
            ->will($this->returnValue($statement));

        $adapter = new Adapter();
        $adapter->setConnection($this->pdo);
        $adapter->setDatabase($database);
    }

    public function testGetConfigDefaults()
    {
        $defaults = array(
            'charset'  => 'utf8mb4',
            'timezone' => '+0:00'
        );

        $adapter = new Adapter();
        $this->assertEquals($defaults, $adapter->getConfig());
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
        $adapter = new Adapter($config);

        $this->assertEquals($expected, $adapter->getConfig());
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
        $adapter = new Adapter($config);

        $this->assertEquals($config, $adapter->getConfig());
    }

    public function testGetConfigValue()
    {
        $host = 'foo.bar.com';
        $adapter = new Adapter(['host' => $host]);
        $this->assertEquals($host, $adapter->getConfigValue('host', '127.0.0.1'));
    }

    public function testGetConfigValueWhenDefaulting()
    {
        $default = '3306';
        $adapter = new Adapter(['host' => 'foo.bar.com']);
        $this->assertEquals($default, $adapter->getConfigValue('port', $default));
    }

    /**
     * @param string $option
     * @param mixed $value
     * @dataProvider settingAdapterOptionsDataProvider
     */
    public function testSettingAdapterOptionsWithoutConnection($option, $value)
    {
        $method  = 'set' . ucfirst($option);
        $adapter = new Adapter();
        $adapter->$method($value);
        $this->assertEquals($value, $adapter->getConfigValue($option, null));
    }

    /**
     * @param string $option
     * @param mixed $value
     * @dataProvider settingAdapterOptionsDataProvider
     */
    public function testSettingAdapterOptionsWithConnection($option, $value)
    {
        $statement = $this->getMock(\PDOStatement::class);
        $statement->expects($this->once())
            ->method('execute')
            ->with($this->contains($value));
        $this->pdo->expects($this->any())
            ->method('prepare')
            ->will($this->returnValue($statement));

        $method  = 'set' . ucfirst($option);
        $adapter = new Adapter();
        $adapter->setConnection($this->pdo);
        $adapter->$method($value);
    }

    public function settingAdapterOptionsDataProvider()
    {
        return [
            ['charset', 'iso-8859-1'],
            ['charset', 'utf8'],
            ['timezone', '+05:00'],
            ['timezone', '+03:00']
        ];
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

        $adapter = new Adapter();
        $adapter->setConnection($this->pdo);
        $this->assertTrue($adapter->ping());
    }

    /**
     * @covers Phlib\Db\Adapter::ping
     */
    public function testFailedPing()
    {
        $this->pdo->expects($this->any())
            ->method('prepare')
            ->will($this->throwException(new \Exception));

        $adapter = new Adapter();
        $adapter->setConnection($this->pdo);
        $this->assertFalse($adapter->ping());
    }

    public function testLastInsertIdNoTableName()
    {
        $this->pdo->expects($this->once())
            ->method('lastInsertId')
            ->with($this->equalTo(null));

        $adapter = new Adapter();
        $adapter->setConnection($this->pdo);
        $adapter->lastInsertId();
    }

    public function testLastInsertIdWithTableName()
    {
        $tableName = 'table';
        $this->pdo->expects($this->once())
            ->method('lastInsertId')
            ->with($this->equalTo($tableName));

        $adapter = new Adapter();
        $adapter->setConnection($this->pdo);
        $adapter->lastInsertId($tableName);
    }

    public function testPrepare()
    {
        $pdoStatement = $this->getMock('\PDOStatement');
        $sql = 'SELECT * FROM table';
        $this->pdo->expects($this->once())
            ->method('prepare')
            ->with($this->equalTo($sql))
            ->will($this->returnValue($pdoStatement));

        $adapter = new Adapter();
        $adapter->setConnection($this->pdo);
        $this->assertEquals($pdoStatement, $adapter->prepare($sql));
    }

    public function testExecute()
    {
        $sql = 'dummy sql';

        // Returned stmt will have rowCount called
        $pdoStatement = $this->getMock('\PDOStatement');
        $pdoStatement->expects($this->once())
            ->method('rowCount');

        // Exec should call query with the SQL
        $adapter = $this->getMock('\Phlib\Db\Adapter', array('query'));
        $adapter->expects($this->once())
            ->method('query')
            ->with($sql)
            ->will($this->returnValue($pdoStatement));
        $adapter->setConnection($this->pdo);
        $adapter->execute($sql);
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
        $adapter = $this->getMock('\Phlib\Db\Adapter', array('query'));
        $adapter->expects($this->once())
            ->method('query')
            ->with($sql, $bind)
            ->will($this->returnValue($pdoStatement));
        $adapter->setConnection($this->pdo);
        $adapter->execute($sql, $bind);
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

        $adapter = new Adapter();
        $adapter->setConnection($this->pdo);
        $this->assertEquals($pdoStatement,$adapter->query($sql));
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

        $adapter = new Adapter();
        $adapter->setConnection($this->pdo);
        $this->assertEquals($pdoStatement,$adapter->query($sql, $bind));
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
