<?php

namespace Phlib\Db\Tests\Exception;

use Phlib\Db\Exception\InvalidQueryException;
use PHPUnit\Framework\TestCase;

class InvalidQueryExceptionTest extends TestCase
{
    public function testConstructor()
    {
        $exception = new InvalidQueryException('SLECT * FRM foo', []);
        static::assertNotEmpty($exception->getMessage());
    }

    public function testConstructorUsesPreviousException()
    {
        $code = 42000;
        $message = 'SQLSTATE[42000]: Syntax error or access violation: 1064 You have an error in your SQL syntax; ' .
            'check the manual that corresponds to your MySQL server version for the right syntax to use near ' .
            "'FRM foo' at line 1";
        $pdoException = new \PDOException($message, $code);
        $exception = new InvalidQueryException('SELECT * FRM foo', [], $pdoException);
        static::assertStringStartsWith($message, $exception->getMessage());
    }

    public function testGetQuery()
    {
        $query = 'SELECT * FRM foo';
        $exception = new InvalidQueryException($query, []);
        static::assertEquals($query, $exception->getQuery());
    }

    public function testGetBindData()
    {
        $bind = ['foo', 'bar'];
        $exception = new InvalidQueryException('', $bind);
        static::assertEquals($bind, $exception->getBindData());
    }

    public function testSuccessfullyDetectsInvalidSyntaxException()
    {
        $code = 42000;
        $message = 'SQLSTATE[42000]: Syntax error or access violation: 1064 You have an error in your SQL syntax; ' .
            'check the manual that corresponds to your MySQL server version for the right syntax to use near ' .
            "'FRM foo' at line 1";
        $pdoException = new \PDOException($message, $code);
        static::assertTrue(InvalidQueryException::isInvalidSyntax($pdoException));
    }

    public function testDetectsNonSyntaxException()
    {
        $code = 2002;
        $message = 'SQLSTATE[HY000] [2002] Connection reset by peer';
        $pdoException = new \PDOException($message, $code);
        static::assertFalse(InvalidQueryException::isInvalidSyntax($pdoException));
    }
}
