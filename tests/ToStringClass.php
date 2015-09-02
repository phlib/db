<?php

namespace Phlib\Db\Tests;

/**
 * Class ToStringObject
 * @package Phlib\Db
 */
class ToStringClass
{
    /**
     * @var string
     */
    protected $value = '';

    /**
     * @param string $value
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
