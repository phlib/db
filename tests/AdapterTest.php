<?php

namespace Phlib\Db\Tests;

use Phlib\Db\Adapter;

class AdapterTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \PDO|\PHPUnit_Framework_MockObject_MockObject
     */
    private $pdo;

    /**
     * Quote Character
     * @var string
     */
    private $qc = '`';

    public function setUp()
    {
        parent::setUp();
        $this->pdo = $this->createMock(\PDO::class);
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

        $adapter->quote()->value($string);
    }

    public function testGetQuoteHandler()
    {
        $adapter = new Adapter();
        $adapter->setConnection($this->pdo);
        $this->assertInstanceOf(Adapter\QuoteHandler::class, $adapter->quote());
    }

    /**
     * @covers \Phlib\Db\Adapter::setConnection
     */
    public function testSetConnection()
    {
        $adapter = new Adapter();
        $adapter->setConnection($this->pdo);

        $this->assertEquals($this->pdo, $adapter->getConnection());
    }

    /**
     * @covers \Phlib\Db\Adapter::setDatabase
     */
    public function testSetDatabaseMakesDbCall()
    {
        $dbname = 'MyDbName';

        /** @var Adapter|\PHPUnit_Framework_MockObject_MockObject $adapter */
        $adapter = $this->getMockBuilder(Adapter::class)
            ->setMethods(['query'])
            ->getMock();
        $adapter->expects($this->once())
            ->method('query')
            ->with($this->equalTo("USE `$dbname`"));

        $adapter->setConnection($this->pdo);
        $adapter->setDatabase($dbname);
    }

    /**
     * @covers \Phlib\Db\Adapter::setDatabase
     */
    public function testSetDatabaseSetsConfig()
    {
        $dbname = 'MyDbName';
        /** @var Adapter|\PHPUnit_Framework_MockObject_MockObject $adapter */
        $adapter = $this->getMockBuilder(Adapter::class)
            ->setMethods(['query'])
            ->getMock();
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
        $exception = new \PDOException(
            "SQLSTATE[42000]: Syntax error or access violation: 1049 Unknown database '$database'.",
            42000
        );
        $statement = $this->createMock(\PDOStatement::class);
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
        $defaults = [
            'charset'  => 'utf8mb4',
            'timezone' => '+0:00'
        ];

        $adapter = new Adapter();
        $this->assertEquals($defaults, $adapter->getConfig());
    }

    public function testGetConfigMixed()
    {
        $expected = [
            'host'     => 'localhost',
            'username' => 'username',
            'password' => 'password',
            'port'     => '3306',
            'charset'  => 'utf8mb4',
            'timezone' => '+0:00'
        ];

        $config = [
            'host'     => 'localhost',
            'username' => 'username',
            'password' => 'password',
            'port'     => '3306'
        ];
        $adapter = new Adapter($config);

        $this->assertEquals($expected, $adapter->getConfig());
    }

    /**
     * @covers \Phlib\Db\Adapter::getConfig
     */
    public function testGetConfigOverrides()
    {
        $config = [
            'host'     => 'localhost',
            'username' => 'username',
            'password' => 'password',
            'port'     => '3306',
            'charset'  => 'iso-8859-1',
            'timezone' => '+1:00'
        ];
        $adapter = new Adapter($config);

        $this->assertEquals($config, $adapter->getConfig());
    }

    /**
     * @param string $option
     * @param mixed $value
     * @dataProvider settingAdapterOptionsDataProvider
     */
    public function testSettingAdapterOptionsWithConnection($option, $value)
    {
        $statement = $this->createMock(\PDOStatement::class);
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
     * @covers \Phlib\Db\Adapter::ping
     */
    public function testSuccessfulPing()
    {
        $pdoStatement = $this->createMock(\PDOStatement::class);
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
     * @covers \Phlib\Db\Adapter::ping
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
        $pdoStatement = $this->createMock(\PDOStatement::class);
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
        $pdoStatement = $this->createMock(\PDOStatement::class);
        $pdoStatement->expects($this->once())
            ->method('rowCount');

        // Exec should call query with the SQL
        /** @var Adapter|\PHPUnit_Framework_MockObject_MockObject $adapter */
        $adapter = $this->getMockBuilder(Adapter::class)
            ->setMethods(['query'])
            ->getMock();
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
        $pdoStatement = $this->createMock(\PDOStatement::class);
        $pdoStatement->expects($this->once())
            ->method('rowCount');

        // Exec should call query with the SQL
        /** @var Adapter|\PHPUnit_Framework_MockObject_MockObject $adapter */
        $adapter = $this->getMockBuilder(Adapter::class)
            ->setMethods(['query'])
            ->getMock();
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
        $pdoStatement = $this->createMock(\PDOStatement::class);
        $pdoStatement->expects($this->once())
            ->method('execute')
            ->with($this->equalTo([]));
        $this->pdo->expects($this->once())
            ->method('prepare')
            ->with($this->equalTo($sql))
            ->will($this->returnValue($pdoStatement));

        $adapter = new Adapter();
        $adapter->setConnection($this->pdo);
        $this->assertEquals($pdoStatement, $adapter->query($sql));
    }

    public function testQueryWithBind()
    {
        $sql = 'SELECT * FROM table WHERE col1 = ?';
        $bind = ['col1' => 'v1'];
        $pdoStatement = $this->createMock(\PDOStatement::class);
        $pdoStatement->expects($this->once())
            ->method('execute')
            ->with($this->equalTo($bind));
        $this->pdo->expects($this->once())
            ->method('prepare')
            ->with($this->equalTo($sql))
            ->will($this->returnValue($pdoStatement));

        $adapter = new Adapter();
        $adapter->setConnection($this->pdo);
        $this->assertEquals($pdoStatement, $adapter->query($sql, $bind));
    }

    /**
     * @expectedException \Phlib\Db\Exception\InvalidQueryException
     */
    public function testQueryWithInvalidSql()
    {
        $exception = new \PDOException('You have an error in your SQL syntax');
        $statement = $this->createMock(\PDOStatement::class);
        $statement->expects($this->any())
            ->method('execute')
            ->will($this->throwException($exception));
        $this->pdo->expects($this->any())
            ->method('prepare')
            ->will($this->returnValue($statement));

        $adapter = new Adapter();
        $adapter->setConnection($this->pdo);
        $adapter->query('SELEECT * FORM foo');
    }

    public function testQueryReconnectsWhenMysqlHasGoneAway()
    {
        $exception = new \PDOException('MySQL server has gone away');
        $statement = $this->createMock(\PDOStatement::class);
        $statement->expects($this->any())
            ->method('execute')
            ->will($this->throwException($exception));
        $this->pdo->expects($this->any())
            ->method('prepare')
            ->will($this->returnValue($statement));

        $adapter = new Adapter();
        $adapter->setConnection($this->pdo);
        $adapter->setConnectionFactory(function () {
            $statement = $this->createMock(\PDOStatement::class);
            $statement->expects($this->once())
                ->method('execute');
            $pdo = $this->createMock(\PDO::class);
            $pdo->expects($this->any())
                ->method('prepare')
                ->will($this->returnValue($statement));
            return $pdo;
        });
        $adapter->query('SELECT * FROM foo');
    }

    /**
     * @expectedException \Phlib\Db\Exception\RuntimeException
     */
    public function testQueryFailsAfterSuccessfulReconnect()
    {
        $exception = new \PDOException('MySQL server has gone away');
        $statement = $this->createMock(\PDOStatement::class);
        $statement->expects($this->any())
            ->method('execute')
            ->will($this->throwException($exception));
        $this->pdo->expects($this->any())
            ->method('prepare')
            ->will($this->returnValue($statement));

        $adapter = new Adapter();
        $adapter->setConnection($this->pdo);
        $adapter->setConnectionFactory(function () {
            $exception = new \PDOException('failed for some random reason', 1234);
            $statement = $this->createMock(\PDOStatement::class);
            $statement->expects($this->any())
                ->method('execute')
                ->will($this->throwException($exception));
            $pdo = $this->createMock(\PDO::class);
            $pdo->expects($this->any())
                ->method('prepare')
                ->will($this->returnValue($statement));
            return $pdo;
        });
        $adapter->query('SELECT * FROM foo');
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

    public function testCloning()
    {
        $adapter = new Adapter();
        $adapter->setConnection($this->pdo);
        $adapter->setConnectionFactory(function () {
            return $this->createMock(\PDO::class);
        });

        $newAdapter = clone $adapter;
        $this->assertNotSame($adapter, $newAdapter);
    }

    public function testBeginTransaction()
    {
        $this->pdo->expects($this->once())
            ->method('beginTransaction');

        $adapter = new Adapter();
        $adapter->setConnection($this->pdo);
        $adapter->beginTransaction();
    }

    public function testBeginTransactionWhenServerHasGoneAway()
    {
        $exception = new \PDOException('MySQL server has gone away');
        $this->pdo->expects($this->any())
            ->method('beginTransaction')
            ->will($this->throwException($exception));

        $adapter = new Adapter();
        $adapter->setConnection($this->pdo);
        $adapter->setConnectionFactory(function () {
            $pdo = $this->createMock(\PDO::class);
            $pdo->expects($this->once())
                ->method('beginTransaction');
            return $pdo;
        });
        $adapter->beginTransaction();
    }

    /**
     * @expectedException \Phlib\Db\Exception\RuntimeException
     */
    public function testBeginTransactionWhenServerHasGoneAwayAndThenFails()
    {
        $exception = new \PDOException('MySQL server has gone away');
        $this->pdo->expects($this->any())
            ->method('beginTransaction')
            ->will($this->throwException($exception));

        $adapter = new Adapter();
        $adapter->setConnection($this->pdo);
        $adapter->setConnectionFactory(function () {
            $exception = new \PDOException('something else bad happened');
            $pdo = $this->createMock(\PDO::class);
            $pdo->expects($this->any())
                ->method('beginTransaction')
                ->will($this->throwException($exception));
            return $pdo;
        });
        $adapter->beginTransaction();
    }

    public function testCommit()
    {
        $this->pdo->expects($this->once())
            ->method('commit');

        $adapter = new Adapter();
        $adapter->setConnection($this->pdo);
        $adapter->commit();
    }

    public function testRollback()
    {
        $this->pdo->expects($this->once())
            ->method('rollback');

        $adapter = new Adapter();
        $adapter->setConnection($this->pdo);
        $adapter->rollBack();
    }
}
