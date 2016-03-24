<?php

namespace Phlib\Db\Tests\Exception;

use Phlib\Db\Exception\UnknownDatabaseException;

class UnknownDatabaseExceptionTest extends \PHPUnit_Framework_TestCase
{
    public function testCreateReturnsException()
    {
        $exception = UnknownDatabaseException::create('foo', new \PDOException());
        $this->assertInstanceOf(UnknownDatabaseException::class, $exception);
    }

    public function testGetDatabaseName()
    {
        $name = 'foo';
        $exception = new UnknownDatabaseException($name, 'message');
        $this->assertEquals($name, $exception->getDatabaseName());
    }

    public function testGetDatabaseNameWhenCreated()
    {
        $name = 'foo';
        $exception = UnknownDatabaseException::create($name, new \PDOException());
        $this->assertEquals($name, $exception->getDatabaseName());
    }

    public function testCorrectlyEvaluatesPdoExceptionOnPdoConstruct()
    {
        $pdoException = new \PDOException("SQLSTATE[HY000] [1049] Unknown database '<dbname>'", 1049);
        $this->assertTrue(UnknownDatabaseException::isUnknownDatabase($pdoException));
    }

    public function testCorrectlyEvaluatesPdoExceptionOnUseDatabase()
    {
        $pdoException = new \PDOException("SQLSTATE[42000]: Syntax error or access violation: 1049 Unknown database '<dbname>'", '42000');
        $this->assertTrue(UnknownDatabaseException::isUnknownDatabase($pdoException));
    }

    public function testCorrectlyEvaluatesPdoExceptionForNonDatabaseError()
    {
        $pdoException = new \PDOException("SQLSTATE[HY000] [2002] Connection refused", 2002);
        $this->assertFalse(UnknownDatabaseException::isUnknownDatabase($pdoException));
    }
}
