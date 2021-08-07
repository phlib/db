<?php

declare(strict_types=1);

namespace Phlib\Db\Tests\Exception;

class PDOExceptionStub extends \PDOException
{
    /**
     * @param int|string $code
     */
    public function __construct(string $message, $code, \Exception $previous = null)
    {
        parent::__construct($message, 0, $previous);

        /*
         * \PDOException overrides \Exception to define $code as a string
         * so that it can support the 5-char error codes defined in the ANSI SQL standard.
         * In reality, it returns a mixture of int and string, for example:
         * - (int)1044 for access denied
         * - (int)1049 for unknown database
         * - (string)42S02 for unknown table
         * - (string)42000 for syntax error, even though this could be int
         */
        if (is_int($code) && $code > 9999) {
            // Codes with 5+ chars are always strings
            $code = (string)$code;
        } elseif (is_string($code) && strlen($code) < 5 && $code === (string)(int)$code) {
            // Use int for all-numeric codes that are shorter than 5 chars
            $code = (int)$code;
        }
        $this->code = $code;
    }
}
