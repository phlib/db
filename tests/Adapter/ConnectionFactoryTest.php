<?php

namespace Phlib\Db\Tests\Adapter;

use Phlib\Db\Adapter\ConnectionFactory;
use Phlib\Db\Adapter\Config;

class ConnectionFactoryTest extends \PHPUnit_Framework_TestCase
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

    public function setUp()
    {
        $this->config  = $this->createMock(Config::class);
        $this->pdo     = $this->createMock(\PDO::class);
        $this->factory = $this->createPartialMock(ConnectionFactory::class, ['create']);

        parent::setUp();
    }

    public function tearDown()
    {
        parent::tearDown();
        $this->factory = null;
        $this->pdo     = null;
    }

    public function testGettingConnection()
    {
        $this->factory->expects($this->any())
            ->method('create')
            ->will($this->returnValue($this->pdo));

        $pdoStatement = $this->createMock(\PDOStatement::class);
        $this->pdo->expects($this->any())
            ->method('prepare')
            ->will($this->returnValue($pdoStatement));

        $this->config->expects($this->any())
            ->method('getMaximumAttempts')
            ->will($this->returnValue(5));
        $this->assertSame($this->pdo, $this->factory->__invoke($this->config));
    }

    /**
     * @param string $method
     * @param string $value
     * @dataProvider charsetIsSetOnConnectionDataProvider
     */
    public function testCharsetIsSetOnConnection($method, $value)
    {
        $this->factory->expects($this->any())
            ->method('create')
            ->will($this->returnValue($this->pdo));

        $pdoStatement = $this->createMock(\PDOStatement::class);
        $pdoStatement->expects($this->any())
            ->method('execute')
            ->with($this->contains($value));
        $this->pdo->expects($this->any())
            ->method('prepare')
            ->will($this->returnValue($pdoStatement));

        $this->config->expects($this->any())->method('getMaximumAttempts')->will($this->returnValue(1));
        $this->config->expects($this->any())->method($method)->will($this->returnValue($value));
        $this->assertSame($this->pdo, $this->factory->__invoke($this->config));
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
        $this->factory->expects($this->any())
            ->method('create')
            ->will($this->throwException(new \PDOException(
                "SQLSTATE[HY000] [1049] Unknown database '<dbname>'",
                1049
            )));
        $this->config->expects($this->any())
            ->method('getMaximumAttempts')
            ->will($this->returnValue(5));
        $this->factory->__invoke($this->config);
    }

    /**
     * @param int $attempts
     * @dataProvider exceedingNumberOfAttemptsDataProvider
     * @expectedException \Phlib\Db\Exception\RuntimeException
     */
    public function testExceedingNumberOfAttempts($attempts)
    {
        $this->factory->expects($this->any())
            ->method('create')
            ->will($this->throwException(new \PDOException(
                "SQLSTATE[HY000] [1049] Unknown database '<dbname>'",
                1049
            )));
        $this->config->expects($this->any())
            ->method('getMaximumAttempts')
            ->will($this->returnValue($attempts));
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
        $this->factory->expects($this->any())
            ->method('create')
            ->will($this->returnValue($this->pdo));
        $pdoStatement = $this->createMock(\PDOStatement::class);
        $pdoStatement->expects($this->any())
            ->method('execute')
            ->will($this->onConsecutiveCalls(
                $this->throwException(new \PDOException()),
                $this->returnValue(true)
            ));
        $this->pdo->expects($this->any())
            ->method('prepare')
            ->will($this->returnValue($pdoStatement));
        $this->config->expects($this->any())
            ->method('getMaximumAttempts')
            ->will($this->returnValue(2));
        $this->assertSame($this->pdo, $this->factory->__invoke($this->config));
    }
}
