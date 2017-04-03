<?php

namespace Phlib\Db\Tests\Replication;

use Phlib\Db\Replication\Memcache;
use Phlib\Db\Replication\StorageInterface;

class MemcacheTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \Memcache|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $memcache;

    /**
     * @var \Phlib\Db\Replication\Memcache
     */
    protected $storage;

    public function setUp()
    {
        if (!extension_loaded('Memcache')) {
            $this->markTestSkipped();
            return;
        }

        $this->memcache = $this->createMock(\Memcache::class);
        $this->storage  = new Memcache($this->memcache);
        parent::setUp();
    }

    public function tearDown()
    {
        parent::tearDown();
        $this->storage  = null;
        $this->memcache = null;
    }

    public function testImplementsInterface()
    {
        $this->assertInstanceOf(StorageInterface::class, $this->storage);
    }

    public function testGetKeyContainsHost()
    {
        $host = '127.0.0.1';
        $this->assertContains($host, $this->storage->getKey($host));
    }

    public function testGetKeyIsNamespaced()
    {
        $host = '127.0.0.1';
        $this->assertNotEquals($host, $this->storage->getKey($host));
    }

    public function testGetSecondsBehindReturnsValue()
    {
        $seconds = 123;
        $this->memcache->expects($this->any())
            ->method('get')
            ->will($this->returnValue($seconds));

        $this->assertEquals($seconds, $this->storage->getSecondsBehind('test-host'));
    }

    public function testGetSecondsBehindRequestUsingHost()
    {
        $host = 'test-host';
        $this->memcache->expects($this->once())
            ->method('get')
            ->with($this->stringContains($host));

        $this->storage->getSecondsBehind($host);
    }

    public function testSetSecondsBehindReceivesValue()
    {
        $seconds = 123;
        $this->memcache->expects($this->once())
            ->method('set')
            ->with($this->anything(), $this->equalTo($seconds));

        $this->storage->setSecondsBehind('test-host', $seconds);
    }

    public function testSetSecondsBehindRequestUsingHost()
    {
        $host = 'test-host';
        $this->memcache->expects($this->once())
            ->method('set')
            ->with($this->stringContains($host));

        $this->storage->setSecondsBehind($host, 123);
    }

    public function testGetHistoryReturnsArray()
    {
        $history = [123, 123, 123, 23, 23, 3];
        $serialized = serialize($history);
        $this->memcache->expects($this->any())
            ->method('get')
            ->will($this->returnValue($serialized));
        $this->assertEquals($history, $this->storage->getHistory('test-host'));
    }

    public function testGetHistoryUsesHost()
    {
        $host = 'test-host';
        $this->memcache->expects($this->once())
            ->method('get')
            ->with($this->stringContains($host));
        $this->storage->getHistory($host);
    }

    public function testSetHistorySetsString()
    {
        $this->memcache->expects($this->once())
            ->method('set')
            ->with($this->anything(), $this->isType('string'));
        $history = [123, 123, 123, 23, 23, 3];
        $this->storage->setHistory('test-host', $history);
    }

    public function testSetHistoryUsesHost()
    {
        $host = 'test-host';
        $this->memcache->expects($this->once())
            ->method('set')
            ->with($this->stringContains($host));
        $this->storage->setHistory($host, [123, 123, 123, 23, 23, 3]);
    }
}
