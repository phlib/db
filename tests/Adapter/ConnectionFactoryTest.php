<?php

namespace Phlib\Db\Tests\Adapter;

use Phlib\Db\Adapter\ConnectionFactory;
use Phlib\Db\Adapter\Config;
use Phlib\Db\Tests\PdoMock;

class ConnectionFactoryTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Config|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $config;

    /**
     * @var \PDO|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $pdo;

    /**
     * @var ConnectionFactory|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $factory;
    
    public function setUp()
    {
        $this->config  = $this->getMockBuilder(Config::class)->disableOriginalConstructor()->getMock();
        $this->pdo     = $this->getMock(PdoMock::class);
        $this->factory = $this->getMockBuilder(ConnectionFactory::class)
            ->setMethods(['create'])
            ->getMock();

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

        $pdoStatement = $this->getMock(\PDOStatement::class);
        $this->pdo->expects($this->any())
            ->method('prepare')
            ->will($this->returnValue($pdoStatement));

        $this->config->expects($this->any())
            ->method('getMaximumAttempts')
            ->will($this->returnValue(5));
        $this->assertSame($this->pdo, $this->factory->__invoke($this->config));
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
        $pdoStatement = $this->getMock(\PDOStatement::class);
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
