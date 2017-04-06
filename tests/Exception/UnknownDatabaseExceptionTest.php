<?php

namespace Phlib\Db\Tests\Exception;

use Phlib\Db\Exception\UnknownDatabaseException;

class UnknownDatabaseExceptionTest extends \PHPUnit_Framework_TestCase
{
    public function testCreateReturnsException()
    {
        $exception = UnknownDatabaseException::createFromUnknownDatabase('foo', new \PDOException());
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
        $exception = UnknownDatabaseException::createFromUnknownDatabase($name, new \PDOException());
        $this->assertEquals($name, $exception->getDatabaseName());
    }

    public function testCorrectlyEvaluatesPdoExceptionOnPdoConstruct()
    {
        $pdoException = new \PDOException("SQLSTATE[HY000] [1049] Unknown database '<dbname>'", 1049);
        $this->assertTrue(UnknownDatabaseException::isUnknownDatabase($pdoException));
    }

    public function testCorrectlyEvaluatesPdoExceptionOnUseDatabase()
    {
        $code = '42000';
        $message = "SQLSTATE[42000]: Syntax error or access violation: 1049 Unknown database '<dbname>'";
        $pdoException = new \PDOException($message, $code);
        $this->assertTrue(UnknownDatabaseException::isUnknownDatabase($pdoException));
    }

    public function testCorrectlyEvaluatesPdoExceptionForNonDatabaseError()
    {
        $code = '42000';
        $message = "SQLSTATE[42000]: Syntax error or access violation: 1064 You have an error in your SQL syntax; " .
            "check the manual that corresponds to your MySQL server version for the right syntax to use near " .
            "'FRM foo' at line 1";
        $pdoException = new \PDOException($message, $code);
        $this->assertFalse(UnknownDatabaseException::isUnknownDatabase($pdoException));
    }
}
