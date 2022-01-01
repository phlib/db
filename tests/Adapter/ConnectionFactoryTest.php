<?php

declare(strict_types=1);

namespace Phlib\Db\Tests\Adapter;

use Phlib\Db\Adapter\Config;
use Phlib\Db\Adapter\ConnectionFactory;
use Phlib\Db\Exception\RuntimeException;
use Phlib\Db\Exception\UnknownDatabaseException;
use Phlib\Db\Tests\Exception\PDOExceptionStub;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ConnectionFactoryTest extends TestCase
{
    /**
     * @var Config|MockObject
     */
    private MockObject $config;

    /**
     * @var \PDO|MockObject
     */
    private MockObject $pdo;

    /**
     * @var ConnectionFactory|MockObject
     */
    private MockObject $factory;

    protected function setUp(): void
    {
        $this->config = $this->createMock(Config::class);
        $this->pdo = $this->createMock(\PDO::class);
        $this->factory = $this->createPartialMock(ConnectionFactory::class, ['create']);

        parent::setUp();
    }

    public function testGettingConnection(): void
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

    public function testConnectionSetsCharsetTimezone(): void
    {
        $charset = 'latin1';
        $timezone = '+0200';

        $this->factory->method('create')
            ->willReturn($this->pdo);

        $testSet = function (array $bind) use ($charset, $timezone) {
            static::assertCount(2, $bind);
            static::assertSame($charset, $bind[0]);
            static::assertSame($timezone, $bind[1]);
            return true;
        };

        $pdoStatement = $this->createMock(\PDOStatement::class);
        $pdoStatement->expects(static::once())
            ->method('execute')
            ->with(static::callback($testSet));
        $this->pdo->expects(static::once())
            ->method('prepare')
            ->with('SET NAMES ?, time_zone = ?')
            ->willReturn($pdoStatement);

        $this->config->expects(static::once())
            ->method('getMaximumAttempts')
            ->willReturn(1);
        $this->config->expects(static::once())
            ->method('getCharset')
            ->willReturn($charset);
        $this->config->expects(static::once())
            ->method('getTimezone')
            ->willReturn($timezone);

        static::assertSame($this->pdo, $this->factory->__invoke($this->config));
    }

    public function testSettingUnknownDatabase(): void
    {
        $this->expectException(UnknownDatabaseException::class);

        $this->factory->method('create')
            ->willThrowException(new PDOExceptionStub(
                "SQLSTATE[HY000] [1049] Unknown database '<dbname>'",
                1049
            ));
        $this->config->method('getMaximumAttempts')
            ->willReturn(5);
        $this->factory->__invoke($this->config);
    }

    /**
     * @dataProvider exceedingNumberOfAttemptsDataProvider
     */
    public function testExceedingNumberOfAttempts(int $attempts): void
    {
        $this->expectException(RuntimeException::class);

        $this->factory->method('create')
            ->willThrowException(new PDOExceptionStub(
                "SQLSTATE[HY000] [1049] Unknown database '<dbname>'",
                1049
            ));
        $this->config->method('getMaximumAttempts')
            ->willReturn($attempts);
        $this->factory->__invoke($this->config);
    }

    public function exceedingNumberOfAttemptsDataProvider(): array
    {
        return [
            [1],
            [2],
            [3],
        ];
    }

    public function testFailedAttemptThenSucceeds(): void
    {
        $this->factory->method('create')
            ->willReturn($this->pdo);
        $pdoStatement = $this->createMock(\PDOStatement::class);
        $pdoStatement->expects(static::exactly(2))
            ->method('execute')
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
