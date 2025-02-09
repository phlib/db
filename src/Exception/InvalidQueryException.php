<?php

declare(strict_types=1);

namespace Phlib\Db\Exception;

class InvalidQueryException extends RuntimeException implements Exception
{
    public static function isInvalidSyntax(\PDOException $exception): bool
    {
        return stripos($exception->getMessage(), 'You have an error in your SQL syntax') !== false;
    }

    public function __construct(
        private readonly string $query,
        private readonly array $bind = [],
        ?\PDOException $previous = null,
    ) {
        $message = 'You have an error in your SQL syntax.';
        $code = 0;
        if ($previous !== null) {
            $message = $previous->getMessage();
            $code = (int)$previous->getCode();
        }
        $message .= ' SQL: ' . $this->query . ' Bind: ' . var_export($this->bind, true);

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
