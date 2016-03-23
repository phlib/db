<?php

namespace Phlib\Db\Exception;

class RuntimeException extends \PDOException implements Exception
{
    /**
     * @param \Exception $exception
     * @return static
     */
    public static function create(\Exception $exception)
    {
        return new static($exception->getMessage(), $exception->getCode(), $exception);
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
