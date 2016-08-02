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

    /**
     * @link http://php.net/manual/en/class.pdoexception.php see property $code type
     */
    public function testCreateWithWeirdPdoExceptionCodeStringType()
    {
        $pdoException = new PDOExceptionStub('Unknown or incorrect', 'HY000');
        $this->assertInstanceOf(RuntimeException::class, RuntimeException::createFromException($pdoException));
    }

    public function testCreateWithWeirdPdoExceptionCodeDocumentGetCodeReturnValue()
    {
        $code = 'HY000';
        $pdoException = new PDOExceptionStub('Unknow or incorrect', $code);
        $newException = RuntimeException::createFromException($pdoException);
        $this->assertEquals($code, $newException->getCode());
    }
}
