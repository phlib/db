<?php

namespace Phlib\Db\Exception;

class RuntimeException extends \PDOException implements Exception
{
    public static function hasServerGoneAway($exception)
    {
        return stripos($exception->getMessage(), 'MySQL server has gone away') !== false;
    }
}
