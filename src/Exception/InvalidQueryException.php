<?php

namespace Phlib\Db\Exception;

class InvalidQueryException extends RuntimeException implements Exception
{
    /**
     * @var string
     */
    private $query;

    /**
     * @var array
     */
    private $bind;

    /**
     * @return bool
     */
    public static function isInvalidSyntax(\PDOException $exception)
    {
        return stripos($exception->getMessage(), 'You have an error in your SQL syntax') !== false;
    }

    /**
     * @param string $query
     */
    public function __construct($query, array $bind = [], \PDOException $previous = null)
    {
        $this->query = $query;
        $this->bind = $bind;

        $message = 'You have an error in your SQL syntax.';
        $code = 0;
        if ($previous !== null) {
            $message = $previous->getMessage();
            $code = $previous->getCode();
        }
        $message .= ' SQL: ' . $query . ' Bind: ' . var_export($bind, true);

        parent::__construct($message, $code, $previous);
    }

    /**
     * @return string
     */
    public function getQuery()
    {
        return $this->query;
    }

    /**
     * @return array
     */
    public function getBindData()
    {
        return $this->bind;
    }
}
