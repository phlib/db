<?php

declare(strict_types=1);

namespace Phlib\Db\Tests;

use Phlib\Db\Adapter;
use Phlib\Db\Exception\InvalidQueryException;
use Phlib\Db\Exception\RuntimeException;
use Phlib\Db\Exception\UnknownDatabaseException;
use Phlib\Db\Tests\Exception\PDOExceptionStub;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class AdapterTest extends TestCase
{
    private MockObject $pdo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pdo = $this->createMock(\PDO::class);
    }

    public function testQuoteHandlerIsSetupCorrectly(): void
    {
        $string = 'foo';
        $expected = "'{$string}'";
        $this->pdo->expects(static::once())
            ->method('quote')
            ->with($string)
            ->willReturn($expected);

        $adapter = new Adapter();
        $adapter->setConnection($this->pdo);

        $actual = $adapter->quote()->value($string);

        static::assertSame($expected, $actual);
    }

    public function testGetQuoteHandler(): void
    {
        $adapter = new Adapter();
        $adapter->setConnection($this->pdo);
        static::assertInstanceOf(Adapter\QuoteHandler::class, $adapter->quote());
    }

    /**
     * @covers \Phlib\Db\Adapter::setConnection
     */
    public function testSetConnection(): void
    {
        $adapter = new Adapter();
        $adapter->setConnection($this->pdo);

        static::assertEquals($this->pdo, $adapter->getConnection());
    }

    /**
     * @covers \Phlib\Db\Adapter::setDatabase
     */
    public function testSetDatabaseMakesDbCall(): void
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
    public function testSetDatabaseSetsConfig(): void
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

    public function testSetDatabaseWhenItsUnknown(): void
    {
        $this->expectException(UnknownDatabaseException::class);

        $database = 'foobar';
        $exception = new PDOExceptionStub(
            "SQLSTATE[42000]: Syntax error or access violation: 1049 Unknown database '{$database}'.",
            '42000'
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

    public function testGetConfigDefaults(): void
    {
        $defaults = [
            'charset' => 'utf8mb4',
            'timezone' => '+0:00',
        ];

        $adapter = new Adapter();
        static::assertEquals($defaults, $adapter->getConfig());
    }

    public function testGetConfigMixed(): void
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
    public function testGetConfigOverrides(): void
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
     * @param mixed $value
     * @dataProvider settingAdapterOptionsDataProvider
     */
    public function testSettingAdapterOptionsWithConnection(string $option, $value): void
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

    public function settingAdapterOptionsDataProvider(): array
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
    public function testSuccessfulPing(): void
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
    public function testFailedPing(): void
    {
        $this->pdo->method('prepare')
            ->willThrowException(new \Exception());

        $adapter = new Adapter();
        $adapter->setConnection($this->pdo);
        static::assertFalse($adapter->ping());
    }

    public function testLastInsertIdNoTableName(): void
    {
        $insertId = (string)rand();
        $this->pdo->expects(static::once())
            ->method('lastInsertId')
            ->with(null)
            ->willReturn($insertId);

        $adapter = new Adapter();
        $adapter->setConnection($this->pdo);
        $actual = $adapter->lastInsertId();

        static::assertSame($insertId, $actual);
    }

    public function testLastInsertIdWithTableName(): void
    {
        $insertId = (string)rand();
        $tableName = 'table';
        $this->pdo->expects(static::once())
            ->method('lastInsertId')
            ->with($tableName)
            ->willReturn($insertId);

        $adapter = new Adapter();
        $adapter->setConnection($this->pdo);
        $actual = $adapter->lastInsertId($tableName);

        static::assertSame($insertId, $actual);
    }

    public function testPrepare(): void
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

    public function testExecute(): void
    {
        $sql = 'dummy sql';
        $rowCount = rand();

        // Returned stmt will have rowCount called
        $pdoStatement = $this->createMock(\PDOStatement::class);
        $pdoStatement->expects(static::once())
            ->method('rowCount')
            ->willReturn($rowCount);

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
        $actual = $adapter->execute($sql);

        static::assertSame($rowCount, $actual);
    }

    public function testExecuteBind(): void
    {
        $sql = 'dummy sql';
        $bind = [1, 2, 3];
        $rowCount = rand();

        // Returned stmt will have rowCount called
        $pdoStatement = $this->createMock(\PDOStatement::class);
        $pdoStatement->expects(static::once())
            ->method('rowCount')
            ->willReturn($rowCount);

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
        $actual = $adapter->execute($sql, $bind);

        static::assertSame($rowCount, $actual);
    }

    public function testQueryNoBind(): void
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

    public function testQueryWithBind(): void
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

    public function testQueryWithInvalidSql(): void
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

    public function testQueryReconnectsWhenMysqlHasGoneAway(): void
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

    public function testQueryFailsAfterSuccessfulReconnect(): void
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
            $exception = new PDOExceptionStub('failed for some random reason', 1234);
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

    public function testSetBufferedConnectionToUnbuffered(): void
    {
        $this->pdo->expects(static::once())
            ->method('setAttribute')
            ->with(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false)
            ->willReturn(true);

        $adapter = new Adapter();
        $adapter->setConnection($this->pdo);
        $adapter->disableBuffering();
    }

    public function testSetUnbufferedConnectionToBuffered(): void
    {
        $this->pdo->expects(static::once())
            ->method('setAttribute')
            ->with(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true)
            ->willReturn(true);

        $adapter = new Adapter();
        $adapter->setConnection($this->pdo);
        $adapter->enableBuffering();
    }

    public function testIsBufferedConnection(): void
    {
        $this->pdo->expects(static::once())
            ->method('getAttribute')
            ->with(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY)
            ->willReturn(true);

        $adapter = new Adapter();
        $adapter->setConnection($this->pdo);
        static::assertTrue($adapter->isBuffered());
    }

    public function testCloning(): void
    {
        $adapter = new Adapter();
        $adapter->setConnection($this->pdo);
        $adapter->setConnectionFactory(function () {
            return $this->createMock(\PDO::class);
        });

        $newAdapter = clone $adapter;
        static::assertNotSame($adapter, $newAdapter);
    }

    public function testBeginTransaction(): void
    {
        $this->pdo->expects(static::once())
            ->method('beginTransaction')
            ->willReturn(true);

        $adapter = new Adapter();
        $adapter->setConnection($this->pdo);
        $actual = $adapter->beginTransaction();

        static::assertTrue($actual);
    }

    public function testBeginTransactionWhenServerHasGoneAway(): void
    {
        $exception = new \PDOException('MySQL server has gone away');
        $this->pdo->method('beginTransaction')
            ->willThrowException($exception);

        $adapter = new Adapter();
        $adapter->setConnection($this->pdo);
        $adapter->setConnectionFactory(function () {
            $pdo = $this->createMock(\PDO::class);
            $pdo->expects(static::once())
                ->method('beginTransaction')
                ->willReturn(true);
            return $pdo;
        });
        $actual = $adapter->beginTransaction();

        static::assertTrue($actual);
    }

    public function testBeginTransactionWhenServerHasGoneAwayAndThenFails(): void
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

    public function testCommit(): void
    {
        $this->pdo->expects(static::once())
            ->method('commit')
            ->willReturn(true);

        $adapter = new Adapter();
        $adapter->setConnection($this->pdo);
        $actual = $adapter->commit();

        static::assertTrue($actual);
    }

    public function testRollback(): void
    {
        $this->pdo->expects(static::once())
            ->method('rollback')
            ->willReturn(true);

        $adapter = new Adapter();
        $adapter->setConnection($this->pdo);
        $actual = $adapter->rollBack();

        static::assertTrue($actual);
    }
}
