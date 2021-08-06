<?php

namespace Phlib\Db\Tests;

use Phlib\Db\Adapter;
use Phlib\Db\Exception\InvalidQueryException;
use Phlib\Db\Exception\RuntimeException;
use Phlib\Db\Exception\UnknownDatabaseException;
use PHPUnit\Framework\TestCase;

/**
 * @group integration
 */
class IntegrationTest extends TestCase
{
    /**
     * @var Adapter
     */
    private $adapter;

    protected function setUp()
    {
        if ((bool)getenv('INTEGRATION_ENABLED') !== true) {
            static::markTestSkipped();
            return;
        }

        parent::setUp();

        $this->adapter = new Adapter([
            'host' => getenv('INTEGRATION_HOST'),
            'port' => getenv('INTEGRATION_PORT'),
            'username' => getenv('INTEGRATION_USERNAME'),
            'password' => getenv('INTEGRATION_PASSWORD'),
        ]);
    }

    public function testPing()
    {
        static::assertTrue($this->adapter->ping());
    }

    public function testQuery()
    {
        $expected = rand();

        $stmt = $this->adapter->query('SELECT ' . $expected);
        $result = $stmt->fetchColumn();

        static::assertSame((string)$expected, $result);
    }

    public function testRuntimeException()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Access denied');
        $this->expectExceptionCode(1045);

        $adapter = new Adapter([
            'host' => getenv('INTEGRATION_HOST'),
            'port' => getenv('INTEGRATION_PORT'),
            'username' => 'invalid_user',
            'password' => 'invalid_pass',
        ]);

        $adapter->getConnection();
    }

    public function testInvalidQueryException()
    {
        $this->expectException(InvalidQueryException::class);
        $this->expectExceptionMessage('error in your SQL syntax');
        $this->expectExceptionCode(42000);

        $this->adapter->query('SELECT foo FROM bar WHERE');
    }

    public function testSetDatabaseNotConnectedSuccess()
    {
        // Connection not active, will just update config
        $this->adapter->setDatabase(getenv('INTEGRATION_DATABASE'));

        // Connection made, no exception for set database
        static::assertTrue($this->adapter->ping());
    }

    public function testSetDatabaseAlreadyConnectedSuccess()
    {
        // Make sure connected
        static::assertTrue($this->adapter->ping());

        // No exception should be thrown for valid database
        $this->adapter->setDatabase(getenv('INTEGRATION_DATABASE'));

        // Still connected
        static::assertTrue($this->adapter->ping());
    }

    /**
     * This test needs the user to have global privileges, otherwise the error will be 'access denied'
     */
    public function testSetDatabaseNotConnectedFail()
    {
        // Connection not active, will just update config, no exception
        $this->adapter->setDatabase('database_does_not_exist');

        // Trigger connection, exception should be thrown
        $this->expectException(UnknownDatabaseException::class);
        $this->expectExceptionCode(UnknownDatabaseException::ER_BAD_DB_ERROR_1);
        $this->adapter->query('SELECT 1');
    }

    /**
     * This test needs the user to have global privileges, otherwise the error will be 'access denied'
     */
    public function testSetDatabaseAlreadyConnectedFail()
    {
        // Make sure connected
        static::assertTrue($this->adapter->ping());

        $this->expectException(UnknownDatabaseException::class);
        $this->expectExceptionCode(UnknownDatabaseException::ER_BAD_DB_ERROR_1);
        $this->adapter->setDatabase('database_does_not_exist');
    }
}
