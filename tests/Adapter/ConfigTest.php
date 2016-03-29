<?php

namespace Phlib\Db\Tests\Adapter;

use Phlib\Db\Adapter\Config;
use Phlib\Db\Exception\InvalidArgumentException;

class ConfigTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @param array $dsnConfig
     * @param $expectedElement
     * @dataProvider getDsnDataProvider
     */
    public function testGetDsn(array $dsnConfig, $expectedElement)
    {
        $config = new Config($dsnConfig);
        $this->assertContains($dsnConfig[$expectedElement], $config->getDsn());
    }

    public function getDsnDataProvider()
    {
        return [
            [['host' => '127.0.0.1'], 'host'],
            [['host' => '127.0.0.1', 'port' => '3307'], 'port'],
            [['host' => '127.0.0.1', 'dbname' => 'foo'], 'dbname'],
        ];
    }

    /**
     * @expectedException \Phlib\Db\Exception\InvalidArgumentException
     */
    public function testGetDsnWithoutHost()
    {
        $config = new Config([]);
        $config->getDsn();
    }

    /**
     * @param string $element
     * @param mixed $value
     * @dataProvider getMethodsDataProvider
     */
    public function testGetMethods($method, $element, $value)
    {
        $config = new Config([$element => $value]);
        $this->assertEquals($value, $config->$method());
    }

    public function getMethodsDataProvider()
    {
        return [
            ['getUsername', 'username', 'foo'],
            ['getPassword', 'password', 'foo'],
            ['getDatabase', 'dbname', 'foo'],
            ['getCharset', 'charset', 'foo'],
            ['getTimezone', 'timezone', 'foo'],
        ];
    }

    /**
     * @param array $data
     * @dataProvider getOptionsDataProvider
     */
    public function testGetOptions(array $data, $element, $expected)
    {
        $options = (new Config($data))->getOptions();
        $this->assertArrayHasKey($element, $options);
        $this->assertEquals($expected, $options[$element]);
    }

    public function getOptionsDataProvider()
    {
        return [
            [['timeout' => 10], \PDO::ATTR_TIMEOUT, 10],
            [['timeout' => 1], \PDO::ATTR_TIMEOUT, 1],
            [['timeout' => 0], \PDO::ATTR_TIMEOUT, 0], // min
            [['timeout' => ''], \PDO::ATTR_TIMEOUT, 2], // default
            [['timeout' => 121], \PDO::ATTR_TIMEOUT, 2], // max
            [[], \PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION],
            [[], \PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC]
        ];
    }

    public function testGetMaximumAttempts()
    {
    }
}
