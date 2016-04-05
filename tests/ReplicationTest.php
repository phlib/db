<?php

namespace Phlib\Db\Tests;

use Phlib\Db\Adapter;
use Phlib\Db\Replication;
use Phlib\Db\Replication\StorageInterface;
use phpmock\phpunit\PHPMock;

class ReplicationTest extends \PHPUnit_Framework_TestCase
{
    use PHPMock;

    /**
     * @var \Phlib\Db\Adapter|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $master;

    /**
     * @var \Phlib\Db\Replication\StorageInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $storage;

    protected function setUp()
    {
        $this->master = $this->getMockBuilder(AdapterMock::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->master->expects($this->any())
            ->method('getConfig')
            ->will($this->returnValue(['host' => '127.0.0.1']));

        $this->storage = $this->getMock(StorageInterface::class);

        parent::setUp();
    }

    public function tearDown()
    {
        parent::tearDown();
        $this->storage = null;
        $this->master  = null;
    }

    public function testCreateFromConfigSuccessfully()
    {
        $config = $this->getDefaultConfig();
        $replication = Replication::createFromConfig($config);
        $this->assertInstanceOf(Replication::class, $replication);
    }

    /**
     * @expectedException \Phlib\Db\Exception\InvalidArgumentException
     */
    public function testCreateFromConfigWithInvalidStorageClass()
    {
        $config = $this->getDefaultConfig();
        $config['storage']['class'] = '\My\Unknown\Class';
        Replication::createFromConfig($config);
    }

    /**
     * @expectedException \Phlib\Db\Exception\InvalidArgumentException
     */
    public function testCreateFromConfigWithInvalidStorageMethod()
    {
        $config = $this->getDefaultConfig();
        $config['storage']['class'] = '\stdClass';
        Replication::createFromConfig($config);
    }


    public function getDefaultConfig()
    {
        return [
            'host'     => '10.0.0.1',
            'username' => 'foo',
            'password' => 'bar',
            'dbname'   => 'test',
            'slaves'   => [
                [
                    'host'     => '10.0.0.2',
                    'username' => 'foo',
                    'password' => 'bar'
                ]
            ],
            'storage' => [
                'class' => \Phlib\Db\Tests\Replication\StorageMock::class,
                'args'  => [[]]
            ],
        ];
    }

    /**
     * @expectedException \Phlib\Db\Exception\InvalidArgumentException
     */
    public function testConstructDoesNotAllowEmptySlaves()
    {
        new Replication($this->master, [], $this->storage);
    }

    public function testGettingStorageReturnsSameInstance()
    {
        $slave = $this->getMock(Adapter::class);
        $replication = new Replication($this->master, [$slave], $this->storage);
        $this->assertSame($this->storage, $replication->getStorage());
    }

    /**
     * @expectedException \Phlib\Db\Exception\InvalidArgumentException
     */
    public function testConstructChecksSlaves()
    {
        $slaves = [new \stdClass()];
        new Replication($this->master, $slaves, $this->storage);
    }

    public function testSetWeighting()
    {
        $weighting = 12345;
        $replication = new Replication($this->master, [$this->getMock(Adapter::class)], $this->storage);
        $replication->setWeighting($weighting);
        $this->assertEquals($weighting, $replication->getWeighting());
    }

    public function testSetMaximumSleep()
    {
        $maxSleep = 123456;
        $replication = new Replication($this->master, [$this->getMock(Adapter::class)], $this->storage);
        $replication->setMaximumSleep($maxSleep);
        $this->assertEquals($maxSleep, $replication->getMaximumSleep());
    }

    /**
     * @param string $method
     * @dataProvider monitorRecordsToStorageDataProvider
     */
    public function testMonitorRecordsToStorage($method)
    {
        $this->storage->expects($this->once())->method($method);
        $slave = $this->getMock(Adapter::class);
        $this->setupSlave($slave, ['Seconds_Behind_Master' => 20]);
        $replication = new Replication($this->master, [$slave], $this->storage);
        $replication->monitor();
    }

    public function monitorRecordsToStorageDataProvider()
    {
        return [
            ['setSecondsBehind'],
            ['setHistory']
        ];
    }

    public function testHistoryGetsTrimmed()
    {
        $maxEntries = Replication::MAX_HISTORY;
        
        $history = array_pad([], $maxEntries, 20);
        $slave   = $this->getMock(Adapter::class);
        $this->setupSlave($slave, ['Seconds_Behind_Master' => 5]);

        $this->storage->expects($this->any())
            ->method('getHistory')
            ->will($this->returnValue($history));

        $this->storage->expects($this->once())
            ->method('setHistory')
            ->with($this->anything(), $this->countOf($maxEntries));

        $replication = new Replication($this->master, [$slave], $this->storage);
        $replication->monitor();
    }

    public function testHistoryGetsNewSlaveValue()
    {
        $maxEntries = Replication::MAX_HISTORY;
        $newValue   = 5;

        $history = array_pad([], $maxEntries / 2, 20);
        $slave   = $this->getMock(Adapter::class);
        $this->setupSlave($slave, ['Seconds_Behind_Master' => $newValue]);

        $this->storage->expects($this->any())
            ->method('getHistory')
            ->will($this->returnValue($history));

        $this->storage->expects($this->once())
            ->method('setHistory')
            ->with($this->anything(), $this->contains($newValue));

        $replication = new Replication($this->master, [$slave], $this->storage);
        $replication->monitor();
    }

    public function testFetchStatusMakesCorrectCall()
    {
        $pdoStatement = $this->getMock(\PDOStatement::class);
        $pdoStatement->expects($this->any())
            ->method('fetch')
            ->will($this->returnValue(['Seconds_Behind_Master' => 10]));

        $slave = $this->getMock(Adapter::class);
        $slave->expects($this->once())
            ->method('query')
            ->with($this->equalTo('SHOW SLAVE STATUS'))
            ->will($this->returnValue($pdoStatement));

        $replication = new Replication($this->master, [$slave], $this->storage);
        $replication->fetchStatus($slave);
    }

    /**
     * @param array $data
     * @expectedException \Phlib\Db\Exception\RuntimeException
     * @dataProvider fetchStatusErrorsWithBadReturnedDataDataProvider
     */
    public function testFetchStatusErrorsWithBadReturnedData($data)
    {
        $pdoStatement = $this->getMock(\PDOStatement::class);
        $pdoStatement->expects($this->any())
            ->method('fetch')
            ->will($this->returnValue($data));

        $slave = $this->getMock(Adapter::class);
        $slave->expects($this->any())
            ->method('query')
            ->will($this->returnValue($pdoStatement));

        $replication = new Replication($this->master, [$slave], $this->storage);
        $replication->fetchStatus($slave);
    }

    public function fetchStatusErrorsWithBadReturnedDataDataProvider()
    {
        return [
            [false],
            [['FooColumn' => 'bar']],
            [['Seconds_Behind_Master' => null]]
        ];
    }

    public function testThrottleWithNoSlaveLag()
    {
        $this->storage->expects($this->any())
            ->method('getSecondsBehind')
            ->will($this->returnValue(0));

        $usleep = $this->getFunctionMock('\Phlib\Db', 'usleep');
        $usleep->expects($this->once())
            ->with($this->equalTo(0));

        $slave = $this->getMock(Adapter::class);
        (new Replication($this->master, [$slave], $this->storage))->throttle();
    }

    public function testThrottleWithSlaveLag()
    {
        $this->storage->expects($this->any())
            ->method('getSecondsBehind')
            ->will($this->returnValue(500));

        $usleep = $this->getFunctionMock('\Phlib\Db', 'usleep');
        $usleep->expects($this->once())
            ->with($this->greaterThan(0));

        $slave = $this->getMock(Adapter::class);
        (new Replication($this->master, [$slave], $this->storage))->throttle();
    }
    
    protected function setupSlave($slave, $return)
    {
        $pdoStatement = $this->getMock(\PDOStatement::class);
        $pdoStatement->expects($this->any())
            ->method('fetch')
            ->will($this->returnValue($return));

        $slave->expects($this->any())
            ->method('query')
            ->will($this->returnValue($pdoStatement));
    }
}
