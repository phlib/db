<?php

declare(strict_types=1);

namespace Phlib\Db\Exception;

class InvalidQueryException extends RuntimeException implements Exception
{
    private string $query;

    private array $bind;

    public static function isInvalidSyntax(\PDOException $exception): bool
    {
        return stripos($exception->getMessage(), 'You have an error in your SQL syntax') !== false;
    }

    public function __construct(string $query, array $bind = [], ?\PDOException $previous = null)
    {
        $this->query = $query;
        $this->bind = $bind;

        $message = 'You have an error in your SQL syntax.';
        $code = 0;
        if ($previous !== null) {
            $message = $previous->getMessage();
            $code = (int)$previous->getCode();
        }
        $message .= ' SQL: ' . $query . ' Bind: ' . var_export($bind, true);

        parent::__construct($message, $code, $previous);
    }

    public function getQuery(): string
    {
        return $this->query;
    }

    public function getBindData(): array
    {
        return $this->bind;
    }
}
