<?php

namespace Phlib\Db\Exception;

class RuntimeException extends \PDOException implements Exception
{
    /**
     * @return static
     */
    public static function createFromException(\PDOException $exception)
    {
        if ($exception instanceof static) {
            return $exception;
        }

        $newSelf = new static($exception->getMessage(), 0, $exception);
        $newSelf->code = $exception->getCode();
        return $newSelf;
    }

    /**
     * @return bool
     */
    public static function hasServerGoneAway(\PDOException $exception)
    {
        return stripos($exception->getMessage(), 'MySQL server has gone away') !== false;
    }
}
