<?php

namespace Phlib\Db\Tests\Adapter;

use Phlib\Db\Adapter\ConnectionFactory;
use Phlib\Db\Adapter\Config;
use PHPUnit\Framework\TestCase;

class ConnectionFactoryTest extends TestCase
{
    /**
     * @var Config|\PHPUnit_Framework_MockObject_MockObject
     */
    private $config;

    /**
     * @var \PDO|\PHPUnit_Framework_MockObject_MockObject
     */
    private $pdo;

    /**
     * @var ConnectionFactory|\PHPUnit_Framework_MockObject_MockObject
     */
    private $factory;

    protected function setUp()
    {
        $this->config  = $this->createMock(Config::class);
        $this->pdo     = $this->createMock(\PDO::class);
        $this->factory = $this->createPartialMock(ConnectionFactory::class, ['create']);

        parent::setUp();
    }

    public function testGettingConnection()
    {
        $this->factory->method('create')
            ->willReturn($this->pdo);

        $pdoStatement = $this->createMock(\PDOStatement::class);
        $this->pdo->method('prepare')
            ->willReturn($pdoStatement);

        $this->config->method('getMaximumAttempts')
            ->willReturn(5);
        static::assertSame($this->pdo, $this->factory->__invoke($this->config));
    }

    /**
     * @param string $method
     * @param string $value
     * @dataProvider charsetIsSetOnConnectionDataProvider
     */
    public function testCharsetIsSetOnConnection($method, $value)
    {
        $this->factory->method('create')
            ->willReturn($this->pdo);

        $pdoStatement = $this->createMock(\PDOStatement::class);
        $pdoStatement->method('execute')
            ->with(static::contains($value));
        $this->pdo->method('prepare')
            ->willReturn($pdoStatement);

        $this->config->method('getMaximumAttempts')
            ->willReturn(1);
        $this->config->method($method)
            ->willReturn($value);
        static::assertSame($this->pdo, $this->factory->__invoke($this->config));
    }

    public function charsetIsSetOnConnectionDataProvider()
    {
        return [
            ['getCharset', 'latin1'],
            ['getTimezone', '+0200']
        ];
    }

    /**
     * @expectedException \Phlib\Db\Exception\UnknownDatabaseException
     */
    public function testSettingUnknownDatabase()
    {
        $this->factory->method('create')
            ->willThrowException(new \PDOException(
                "SQLSTATE[HY000] [1049] Unknown database '<dbname>'",
                1049
            ));
        $this->config->method('getMaximumAttempts')
            ->willReturn(5);
        $this->factory->__invoke($this->config);
    }

    /**
     * @param int $attempts
     * @dataProvider exceedingNumberOfAttemptsDataProvider
     * @expectedException \Phlib\Db\Exception\RuntimeException
     */
    public function testExceedingNumberOfAttempts($attempts)
    {
        $this->factory->method('create')
            ->willThrowException(new \PDOException(
                "SQLSTATE[HY000] [1049] Unknown database '<dbname>'",
                1049
            ));
        $this->config->method('getMaximumAttempts')
            ->willReturn($attempts);
        $this->factory->__invoke($this->config);
    }

    public function exceedingNumberOfAttemptsDataProvider()
    {
        return [
            [1],
            [2],
            [3]
        ];
    }

    public function testFailedAttemptThenSucceeds()
    {
        $this->factory->method('create')
            ->willReturn($this->pdo);
        $pdoStatement = $this->createMock(\PDOStatement::class);
        $pdoStatement->method('execute')
            ->will(static::onConsecutiveCalls(
                static::throwException(new \PDOException()),
                static::returnValue(true)
            ));
        $this->pdo->method('prepare')
            ->willReturn($pdoStatement);
        $this->config->method('getMaximumAttempts')
            ->willReturn(2);
        static::assertSame($this->pdo, $this->factory->__invoke($this->config));
    }
}
