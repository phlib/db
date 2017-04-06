<?php

namespace Phlib\Db\Tests\Exception;

class PDOExceptionStub extends \PDOException
{
    public function __construct($message = '', $code = 0, \Exception $previous = null)
    {
        $this->message  = $message;
        $this->code     = $code;
        $this->previous = $previous;
    }
}
