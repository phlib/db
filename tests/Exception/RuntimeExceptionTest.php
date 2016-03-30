<?php

namespace Phlib\Db\Tests\Exception;

use Phlib\Db\Exception\RuntimeException;

class RuntimeExceptionTest extends \PHPUnit_Framework_TestCase
{
    public function testCreate()
    {
        $pdoException = new \PDOException();
        $this->assertInstanceOf(RuntimeException::class, RuntimeException::createFromException($pdoException));
    }

    public function testSuccessfullyDetectServerHasGoneAway()
    {
        $code = 2006;
        $message = 'SQLSTATE[HY000]: General error: 2006 MySQL server has gone away';
        $pdoException = new \PDOException($message, $code);
        $this->assertTrue(RuntimeException::hasServerGoneAway($pdoException));
    }

    public function testIgnoresOtherPdoExceptions()
    {
        $code = 2002;
        $message = "SQLSTATE[HY000] [2002] Connection reset by peer";
        $pdoException = new \PDOException($message, $code);
        $this->assertFalse(RuntimeException::hasServerGoneAway($pdoException));
    }
}
