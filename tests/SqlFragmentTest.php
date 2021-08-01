<?php

declare(strict_types=1);

namespace Phlib\Db\Tests;

use Phlib\Db\SqlFragment;
use PHPUnit\Framework\TestCase;

class SqlFragmentTest extends TestCase
{
    public function testToString(): void
    {
        $value = 'abc123';
        $sqlFragment = new SqlFragment($value);
        self::assertEquals($value, (string)$sqlFragment);
    }
}
