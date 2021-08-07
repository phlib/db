<?php

declare(strict_types=1);

namespace Phlib\Db\Exception;

class RuntimeException extends \PDOException implements Exception
{
    public static function createFromException(\PDOException $exception): self
    {
        if ($exception instanceof static) {
            return $exception;
        }

        $newSelf = new static($exception->getMessage(), 0, $exception);
        $newSelf->code = $exception->getCode();
        return $newSelf;
    }

    public static function hasServerGoneAway(\PDOException $exception): bool
    {
        return stripos($exception->getMessage(), 'MySQL server has gone away') !== false;
    }
}
