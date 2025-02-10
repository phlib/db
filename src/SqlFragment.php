<?php

declare(strict_types=1);

namespace Phlib\Db;

/**
 * This class will hold a SQL fragment and return it when cast to a string by Db::quote()
 * to avoid the string otherwise being quoted as a value
 */
class SqlFragment implements \Stringable
{
    public function __construct(
        private readonly string $value,
    ) {
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
