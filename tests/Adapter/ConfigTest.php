<?php

declare(strict_types=1);

namespace Phlib\Db\Tests\Adapter;

use Phlib\Db\Adapter\Config;
use Phlib\Db\Exception\InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ConfigTest extends TestCase
{
    /**
     * @dataProvider getDsnDataProvider
     */
    public function testGetDsn(array $dsnConfig, string $expectedElement): void
    {
        $config = new Config($dsnConfig);
        static::assertStringContainsString($dsnConfig[$expectedElement], $config->getDsn());
    }

    public function getDsnDataProvider(): array
    {
        return [
            [['host' => '127.0.0.1'], 'host'],
            [['host' => '127.0.0.1', 'port' => '3307'], 'port'],
            [['host' => '127.0.0.1', 'dbname' => 'foo'], 'dbname'],
        ];
    }

    public function testGetDsnWithoutHost(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $config = new Config([]);
        $config->getDsn();
    }

    /**
     * @param mixed $value
     * @dataProvider getMethodsDataProvider
     */
    public function testGetMethods(string $method, string $element, $value): void
    {
        $config = new Config([
            $element => $value,
        ]);
        static::assertSame($value, $config->{$method}());
    }

    public function getMethodsDataProvider(): array
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
     * @param mixed $expected
     * @dataProvider getOptionsDataProvider
     */
    public function testGetOptions(array $data, int $element, $expected): void
    {
        $options = (new Config($data))->getOptions();
        static::assertArrayHasKey($element, $options);
        static::assertSame($expected, $options[$element]);
    }

    public function getOptionsDataProvider(): array
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
     * @dataProvider getMaximumAttemptsDataProvider
     */
    public function testGetMaximumAttempts(int $value, int $expected): void
    {
        $config = new Config([
            'retryCount' => $value,
        ]);
        static::assertSame($expected, $config->getMaximumAttempts());
    }

    public function getMaximumAttemptsDataProvider(): array
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
