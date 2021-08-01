<?php

namespace Phlib\Db\Tests\Adapter;

use Phlib\Db\Adapter\Config;
use Phlib\Db\Exception\InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ConfigTest extends TestCase
{
    /**
     * @param string $expectedElement
     * @dataProvider getDsnDataProvider
     */
    public function testGetDsn(array $dsnConfig, $expectedElement)
    {
        $config = new Config($dsnConfig);
        static::assertContains($dsnConfig[$expectedElement], $config->getDsn());
    }

    public function getDsnDataProvider()
    {
        return [
            [['host' => '127.0.0.1'], 'host'],
            [['host' => '127.0.0.1', 'port' => '3307'], 'port'],
            [['host' => '127.0.0.1', 'dbname' => 'foo'], 'dbname'],
        ];
    }

    public function testGetDsnWithoutHost()
    {
        $this->expectException(InvalidArgumentException::class);

        $config = new Config([]);
        $config->getDsn();
    }

    /**
     * @param string $method
     * @param string $element
     * @param mixed $value
     * @dataProvider getMethodsDataProvider
     */
    public function testGetMethods($method, $element, $value)
    {
        $config = new Config([
            $element => $value,
        ]);
        static::assertEquals($value, $config->{$method}());
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
     * @param int $element
     * @param string $expected
     * @dataProvider getOptionsDataProvider
     */
    public function testGetOptions(array $data, $element, $expected)
    {
        $options = (new Config($data))->getOptions();
        static::assertArrayHasKey($element, $options);
        static::assertEquals($expected, $options[$element]);
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
            [[], \PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC],
        ];
    }

    /**
     * @param int $expected
     * @param int $value
     * @dataProvider getMaximumAttemptsDataProvider
     */
    public function testGetMaximumAttempts($value, $expected)
    {
        $config = new Config([
            'retryCount' => $value,
        ]);
        static::assertEquals($expected, $config->getMaximumAttempts());
    }

    public function getMaximumAttemptsDataProvider()
    {
        return [
            [-1, 1],
            [0, 1],
            [1, 2],
            [2, 3],
            [10, 11],
            [11, 1],
        ];
    }
}
