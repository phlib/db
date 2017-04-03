<?php

namespace Phlib\Db\Tests;

use Phlib\Db\Adapter;
use Phlib\Db\BigResult;
use Phlib\Db\Exception\InvalidArgumentException;

class BigResultTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \Phlib\Db\Adapter|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $adapter;

    protected function setUp()
    {
        $pdoStatement = $this->createMock(\PDOStatement::class);

        $this->adapter = $this->getMockBuilder(Adapter::class)->getMock();
        $this->adapter->expects($this->any())
            ->method('prepare')
            ->will($this->returnValue($pdoStatement));
        parent::setUp();
    }

    public function tearDown()
    {
        parent::tearDown();
        $this->adapter = null;
    }

    public function testQueryIsSetupForLongQueryTime()
    {
        $queryTime = 123;
        $this->adapter->expects($this->once())
            ->method('query')
            ->with($this->stringContains("long_query_time=$queryTime"));
        (new BigResult($this->adapter, ['long_query_time' => $queryTime]))
            ->query('SELECT');
    }

    public function testQueryIsSetupForWriteTimeout()
    {
        $writeTimeout = 123;
        $this->adapter->expects($this->once())
            ->method('query')
            ->with($this->stringContains("net_write_timeout=$writeTimeout"));
        (new BigResult($this->adapter, ['net_write_timeout' => $writeTimeout]))
            ->query('SELECT');
    }

    public function testQueryDisablesBuffering()
    {
        $this->adapter->expects($this->once())
            ->method('disableBuffering');
        (new BigResult($this->adapter))->query('SELECT');
    }

    public function testQueryReturnsStatement()
    {
        $bigResult = (new BigResult($this->adapter));
        $this->assertInstanceOf(\PDOStatement::class, $bigResult->query('SELECT'));
    }

    public function testCheckForInspectedRowLimitOnSuccess()
    {
        $bigResult = $this->getMockBuilder(BigResult::class)
            ->setConstructorArgs([$this->adapter])
            ->setMethods(['getInspectedRows'])
            ->getMock();
        $bigResult->expects($this->any())
            ->method('getInspectedRows')
            ->will($this->returnValue(5));
        $this->adapter->expects($this->once())
            ->method('query');
        $bigResult->query('SELECT', [], 10);
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testCheckForInspectedRowLimitOnFailure()
    {
        $bigResult = $this->getMockBuilder(BigResult::class)
            ->setConstructorArgs([$this->adapter])
            ->setMethods(['getInspectedRows'])
            ->getMock();
        $bigResult->expects($this->any())
            ->method('getInspectedRows')
            ->will($this->returnValue(10));
        $bigResult->query('SELECT', [], 5);
    }

    public function testStaticExecuteReturnsStatement()
    {
        $this->assertInstanceOf(\PDOStatement::class, BigResult::execute($this->adapter, 'SELECT'));
    }
}
