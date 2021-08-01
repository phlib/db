<?php

namespace Phlib\Db\Tests;

use Phlib\Db\Adapter;
use Phlib\Db\Exception\InvalidQueryException;
use Phlib\Db\Exception\RuntimeException;
use Phlib\Db\Exception\UnknownDatabaseException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class AdapterTest extends TestCase
{
    /**
     * @var \PDO|MockObject
     */
    private $pdo;

    /**
     * Quote Character
     * @var string
     */
    private $qc = '`';

    protected function setUp()
    {
        parent::setUp();
        $this->pdo = $this->createMock(\PDO::class);
    }

    public function testQuoteHandlerIsSetupCorrectly()
    {
        $string = 'foo';
        $this->pdo->expects(static::once())
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
        static::assertInstanceOf(Adapter\QuoteHandler::class, $adapter->quote());
    }

    /**
     * @covers \Phlib\Db\Adapter::setConnection
     */
    public function testSetConnection()
    {
        $adapter = new Adapter();
        $adapter->setConnection($this->pdo);

        static::assertEquals($this->pdo, $adapter->getConnection());
    }

    /**
     * @covers \Phlib\Db\Adapter::setDatabase
     */
    public function testSetDatabaseMakesDbCall()
    {
        $dbname = 'MyDbName';

        /** @var Adapter|MockObject $adapter */
        $adapter = $this->getMockBuilder(Adapter::class)
            ->setMethods(['query'])
            ->getMock();
        $adapter->expects(static::once())
            ->method('query')
            ->with("USE `{$dbname}`");

        $adapter->setConnection($this->pdo);
        $adapter->setDatabase($dbname);
    }

    /**
     * @covers \Phlib\Db\Adapter::setDatabase
     */
    public function testSetDatabaseSetsConfig()
    {
        $dbname = 'MyDbName';
        /** @var Adapter|MockObject $adapter */
        $adapter = $this->getMockBuilder(Adapter::class)
            ->setMethods(['query'])
            ->getMock();
        $adapter->setConnection($this->pdo);
        $adapter->setDatabase($dbname);

        $config = $adapter->getConfig();

        static::assertArrayHasKey('dbname', $config);
        static::assertSame($dbname, $config['dbname']);
    }

    public function testSetDatabaseWhenItsUnknown()
    {
        $this->expectException(UnknownDatabaseException::class);

        $database = 'foobar';
        $exception = new \PDOException(
            "SQLSTATE[42000]: Syntax error or access violation: 1049 Unknown database '{$database}'.",
            42000
        );
        $statement = $this->createMock(\PDOStatement::class);
        $statement->method('execute')
            ->willThrowException($exception);
        $this->pdo->method('prepare')
            ->willReturn($statement);

        $adapter = new Adapter();
        $adapter->setConnection($this->pdo);
        $adapter->setDatabase($database);
    }

    public function testGetConfigDefaults()
    {
        $defaults = [
            'charset' => 'utf8mb4',
            'timezone' => '+0:00',
        ];

        $adapter = new Adapter();
        static::assertEquals($defaults, $adapter->getConfig());
    }

    public function testGetConfigMixed()
    {
        $expected = [
            'host' => 'localhost',
            'username' => 'username',
            'password' => 'password',
            'port' => '3306',
            'charset' => 'utf8mb4',
            'timezone' => '+0:00',
        ];

        $config = [
            'host' => 'localhost',
            'username' => 'username',
            'password' => 'password',
            'port' => '3306',
        ];
        $adapter = new Adapter($config);

        static::assertEquals($expected, $adapter->getConfig());
    }

    /**
     * @covers \Phlib\Db\Adapter::getConfig
     */
    public function testGetConfigOverrides()
    {
        $config = [
            'host' => 'localhost',
            'username' => 'username',
            'password' => 'password',
            'port' => '3306',
            'charset' => 'iso-8859-1',
            'timezone' => '+1:00',
        ];
        $adapter = new Adapter($config);

        static::assertEquals($config, $adapter->getConfig());
    }

    /**
     * @param string $option
     * @param mixed $value
     * @dataProvider settingAdapterOptionsDataProvider
     */
    public function testSettingAdapterOptionsWithConnection($option, $value)
    {
        $statement = $this->createMock(\PDOStatement::class);
        $statement->expects(static::once())
            ->method('execute')
            ->with(static::contains($value));
        $this->pdo->method('prepare')
            ->willReturn($statement);

        $method = 'set' . ucfirst($option);
        $adapter = new Adapter();
        $adapter->setConnection($this->pdo);
        $adapter->{$method}($value);
    }

    public function settingAdapterOptionsDataProvider()
    {
        return [
            ['charset', 'iso-8859-1'],
            ['charset', 'utf8'],
            ['timezone', '+05:00'],
            ['timezone', '+03:00'],
        ];
    }

    /**
     * @covers \Phlib\Db\Adapter::ping
     */
    public function testSuccessfulPing()
    {
        $pdoStatement = $this->createMock(\PDOStatement::class);
        $pdoStatement->method('fetchColumn')
            ->willReturn(1);
        $this->pdo->method('prepare')
            ->willReturn($pdoStatement);

        $adapter = new Adapter();
        $adapter->setConnection($this->pdo);
        static::assertTrue($adapter->ping());
    }

    /**
     * @covers \Phlib\Db\Adapter::ping
     */
    public function testFailedPing()
    {
        $this->pdo->method('prepare')
            ->willThrowException(new \Exception());

        $adapter = new Adapter();
        $adapter->setConnection($this->pdo);
        static::assertFalse($adapter->ping());
    }

    public function testLastInsertIdNoTableName()
    {
        $this->pdo->expects(static::once())
            ->method('lastInsertId')
            ->with(null);

        $adapter = new Adapter();
        $adapter->setConnection($this->pdo);
        $adapter->lastInsertId();
    }

    public function testLastInsertIdWithTableName()
    {
        $tableName = 'table';
        $this->pdo->expects(static::once())
            ->method('lastInsertId')
            ->with($tableName);

        $adapter = new Adapter();
        $adapter->setConnection($this->pdo);
        $adapter->lastInsertId($tableName);
    }

    public function testPrepare()
    {
        $pdoStatement = $this->createMock(\PDOStatement::class);
        $sql = 'SELECT * FROM table';
        $this->pdo->expects(static::once())
            ->method('prepare')
            ->with($sql)
            ->willReturn($pdoStatement);

        $adapter = new Adapter();
        $adapter->setConnection($this->pdo);
        static::assertEquals($pdoStatement, $adapter->prepare($sql));
    }

    public function testExecute()
    {
        $sql = 'dummy sql';

        // Returned stmt will have rowCount called
        $pdoStatement = $this->createMock(\PDOStatement::class);
        $pdoStatement->expects(static::once())
            ->method('rowCount');

        // Exec should call query with the SQL
        /** @var Adapter|MockObject $adapter */
        $adapter = $this->getMockBuilder(Adapter::class)
            ->setMethods(['query'])
            ->getMock();
        $adapter->expects(static::once())
            ->method('query')
            ->with($sql)
            ->willReturn($pdoStatement);
        $adapter->setConnection($this->pdo);
        $adapter->execute($sql);
    }

    public function testExecuteBind()
    {
        $sql = 'dummy sql';
        $bind = [1, 2, 3];

        // Returned stmt will have rowCount called
        $pdoStatement = $this->createMock(\PDOStatement::class);
        $pdoStatement->expects(static::once())
            ->method('rowCount');

        // Exec should call query with the SQL
        /** @var Adapter|MockObject $adapter */
        $adapter = $this->getMockBuilder(Adapter::class)
            ->setMethods(['query'])
            ->getMock();
        $adapter->expects(static::once())
            ->method('query')
            ->with($sql, $bind)
            ->willReturn($pdoStatement);
        $adapter->setConnection($this->pdo);
        $adapter->execute($sql, $bind);
    }

    public function testQueryNoBind()
    {
        $sql = 'SELECT * FROM table';
        $pdoStatement = $this->createMock(\PDOStatement::class);
        $pdoStatement->expects(static::once())
            ->method('execute')
            ->with([]);
        $this->pdo->expects(static::once())
            ->method('prepare')
            ->with($sql)
            ->willReturn($pdoStatement);

        $adapter = new Adapter();
        $adapter->setConnection($this->pdo);
        static::assertEquals($pdoStatement, $adapter->query($sql));
    }

    public function testQueryWithBind()
    {
        $sql = 'SELECT * FROM table WHERE col1 = ?';
        $bind = ['col1' => 'v1'];
        $pdoStatement = $this->createMock(\PDOStatement::class);
        $pdoStatement->expects(static::once())
            ->method('execute')
            ->with($bind);
        $this->pdo->expects(static::once())
            ->method('prepare')
            ->with($sql)
            ->willReturn($pdoStatement);

        $adapter = new Adapter();
        $adapter->setConnection($this->pdo);
        static::assertEquals($pdoStatement, $adapter->query($sql, $bind));
    }

    public function testQueryWithInvalidSql()
    {
        $this->expectException(InvalidQueryException::class);

        $exception = new \PDOException('You have an error in your SQL syntax');
        $statement = $this->createMock(\PDOStatement::class);
        $statement->method('execute')
            ->willThrowException($exception);
        $this->pdo->method('prepare')
            ->willReturn($statement);

        $adapter = new Adapter();
        $adapter->setConnection($this->pdo);
        $adapter->query('SELEECT * FORM foo');
    }

    public function testQueryReconnectsWhenMysqlHasGoneAway()
    {
        $exception = new \PDOException('MySQL server has gone away');
        $statement = $this->createMock(\PDOStatement::class);
        $statement->method('execute')
            ->willThrowException($exception);
        $this->pdo->method('prepare')
            ->willReturn($statement);

        $adapter = new Adapter();
        $adapter->setConnection($this->pdo);
        $adapter->setConnectionFactory(function () {
            $statement = $this->createMock(\PDOStatement::class);
            $statement->expects(static::once())
                ->method('execute');
            $pdo = $this->createMock(\PDO::class);
            $pdo->method('prepare')
                ->willReturn($statement);
            return $pdo;
        });
        $adapter->query('SELECT * FROM foo');
    }

    public function testQueryFailsAfterSuccessfulReconnect()
    {
        $this->expectException(RuntimeException::class);

        $exception = new \PDOException('MySQL server has gone away');
        $statement = $this->createMock(\PDOStatement::class);
        $statement->method('execute')
            ->willThrowException($exception);
        $this->pdo->method('prepare')
            ->willReturn($statement);

        $adapter = new Adapter();
        $adapter->setConnection($this->pdo);
        $adapter->setConnectionFactory(function () {
            $exception = new \PDOException('failed for some random reason', 1234);
            $statement = $this->createMock(\PDOStatement::class);
            $statement->method('execute')
                ->willThrowException($exception);
            $pdo = $this->createMock(\PDO::class);
            $pdo->method('prepare')
                ->willReturn($statement);
            return $pdo;
        });
        $adapter->query('SELECT * FROM foo');
    }

    public function testSetBufferedConnectionToUnbuffered()
    {
        $this->pdo->expects(static::once())
            ->method('setAttribute')
            ->with(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false)
            ->willReturn(true);

        $adapter = new Adapter();
        $adapter->setConnection($this->pdo);
        $adapter->disableBuffering();
    }

    public function testSetUnbufferedConnectionToBuffered()
    {
        $this->pdo->expects(static::once())
            ->method('setAttribute')
            ->with(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true)
            ->willReturn(true);

        $adapter = new Adapter();
        $adapter->setConnection($this->pdo);
        $adapter->enableBuffering();
    }

    public function testIsBufferedConnection()
    {
        $this->pdo->expects(static::once())
            ->method('getAttribute')
            ->with(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY)
            ->willReturn(true);

        $adapter = new Adapter();
        $adapter->setConnection($this->pdo);
        static::assertTrue($adapter->isBuffered());
    }

    public function testCloning()
    {
        $adapter = new Adapter();
        $adapter->setConnection($this->pdo);
        $adapter->setConnectionFactory(function () {
            return $this->createMock(\PDO::class);
        });

        $newAdapter = clone $adapter;
        static::assertNotSame($adapter, $newAdapter);
    }

    public function testBeginTransaction()
    {
        $this->pdo->expects(static::once())
            ->method('beginTransaction');

        $adapter = new Adapter();
        $adapter->setConnection($this->pdo);
        $adapter->beginTransaction();
    }

    public function testBeginTransactionWhenServerHasGoneAway()
    {
        $exception = new \PDOException('MySQL server has gone away');
        $this->pdo->method('beginTransaction')
            ->willThrowException($exception);

        $adapter = new Adapter();
        $adapter->setConnection($this->pdo);
        $adapter->setConnectionFactory(function () {
            $pdo = $this->createMock(\PDO::class);
            $pdo->expects(static::once())
                ->method('beginTransaction');
            return $pdo;
        });
        $adapter->beginTransaction();
    }

    public function testBeginTransactionWhenServerHasGoneAwayAndThenFails()
    {
        $this->expectException(RuntimeException::class);

        $exception = new \PDOException('MySQL server has gone away');
        $this->pdo->method('beginTransaction')
            ->willThrowException($exception);

        $adapter = new Adapter();
        $adapter->setConnection($this->pdo);
        $adapter->setConnectionFactory(function () {
            $exception = new \PDOException('something else bad happened');
            $pdo = $this->createMock(\PDO::class);
            $pdo->method('beginTransaction')
                ->willThrowException($exception);
            return $pdo;
        });
        $adapter->beginTransaction();
    }

    public function testCommit()
    {
        $this->pdo->expects(static::once())
            ->method('commit');

        $adapter = new Adapter();
        $adapter->setConnection($this->pdo);
        $adapter->commit();
    }

    public function testRollback()
    {
        $this->pdo->expects(static::once())
            ->method('rollback');

        $adapter = new Adapter();
        $adapter->setConnection($this->pdo);
        $adapter->rollBack();
    }
}
