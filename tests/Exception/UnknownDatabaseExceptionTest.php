<?php

namespace Phlib\Db\Tests\Exception;

use Phlib\Db\Exception\UnknownDatabaseException;
use PHPUnit\Framework\TestCase;

class UnknownDatabaseExceptionTest extends TestCase
{
    public function testCreateReturnsException()
    {
        $exception = UnknownDatabaseException::createFromUnknownDatabase('foo', new \PDOException());
        static::assertInstanceOf(UnknownDatabaseException::class, $exception);
    }

    public function testGetDatabaseName()
    {
        $name = 'foo';
        $exception = new UnknownDatabaseException($name, 'message');
        static::assertEquals($name, $exception->getDatabaseName());
    }

    public function testGetDatabaseNameWhenCreated()
    {
        $name = 'foo';
        $exception = UnknownDatabaseException::createFromUnknownDatabase($name, new \PDOException());
        static::assertEquals($name, $exception->getDatabaseName());
    }

    public function testCorrectlyEvaluatesPdoExceptionOnPdoConstruct()
    {
        $pdoException = new PDOExceptionStub("SQLSTATE[HY000] [1049] Unknown database '<dbname>'", 1049);
        static::assertTrue(UnknownDatabaseException::isUnknownDatabase($pdoException));
    }

    public function testCorrectlyEvaluatesPdoExceptionOnUseDatabase()
    {
        $code = '42000';
        $message = "SQLSTATE[42000]: Syntax error or access violation: 1049 Unknown database '<dbname>'";
        $pdoException = new PDOExceptionStub($message, $code);
        static::assertTrue(UnknownDatabaseException::isUnknownDatabase($pdoException));
    }

    public function testCorrectlyEvaluatesPdoExceptionForNonDatabaseError()
    {
        $code = '42000';
        $message = 'SQLSTATE[42000]: Syntax error or access violation: 1064 You have an error in your SQL syntax; ' .
            'check the manual that corresponds to your MySQL server version for the right syntax to use near ' .
            "'FRM foo' at line 1";
        $pdoException = new PDOExceptionStub($message, $code);
        static::assertFalse(UnknownDatabaseException::isUnknownDatabase($pdoException));
    }
}
