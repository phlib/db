<?php

namespace Phlib\Db\Tests;

class ToStringClass
{
    /**
     * @var string
     */
    private $value = '';

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
