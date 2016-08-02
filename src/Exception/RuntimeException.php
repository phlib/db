<?php

namespace Phlib\Db\Exception;

class RuntimeException extends \PDOException implements Exception
{
    private $pdoCode;

    /**
     * @param \PDOException $exception
     * @return static
     */
    public static function createFromException(\PDOException $exception)
    {
        $code = $exception->getCode();
        if (!is_numeric($code)) {
            $code = (int)$code;
        }
        $newSelf = new static($exception->getMessage(), $code, $exception);
        $newSelf->pdoCode = $exception->getCode();
        return $newSelf;
    }

    /**
     * @param \PDOException $exception
     * @return bool
     */
    public static function hasServerGoneAway(\PDOException $exception)
    {
        return stripos($exception->getMessage(), 'MySQL server has gone away') !== false;
    }

    /**
     * @return string
     */
    public function getPDOCode()
    {
        return $this->pdoCode;
    }
}
