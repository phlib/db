<?php

namespace Phlib\Db\Tests;

use Phlib\Db\SqlFragment;
use PHPUnit\Framework\TestCase;

class SqlFragmentTest extends TestCase
{
    public function testToString()
    {
        $value = 'abc123';
        $sqlFragment = new SqlFragment($value);
        self::assertEquals($value, (string)$sqlFragment);
    }
}
