<?php

namespace Phlib\Db\Exception;

class RuntimeException extends \PDOException implements Exception
{
    /**
     * @param \PDOException $exception
     * @return static
     */
    public static function createFromException(\PDOException $exception)
    {
        $newSelf = new static($exception->getMessage(), 0, $exception);
        $newSelf->code = $exception->getCode();
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
}
