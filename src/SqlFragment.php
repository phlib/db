<?php

namespace Phlib\Db;

/**
 * This class will hold a SQL fragment and return it when cast to a string by Db::quote()
 * to avoid the string otherwise being quoted as a value
 */
class SqlFragment
{
    /**
     * @var string
     */
    private $value;

    /**
     * @param string $value SQL expression
     */
    public function __construct($value)
    {
        $this->value = (string)$value;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->value;
    }
}
