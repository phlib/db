<?php

declare(strict_types=1);

namespace Phlib\Db\Tests\Exception;

use Phlib\Db\Exception\InvalidQueryException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class InvalidQueryExceptionTest extends TestCase
{
    public function testConstructor(): void
    {
        $exception = new InvalidQueryException('SLECT * FRM foo', []);
        static::assertNotEmpty($exception->getMessage());
    }

    public function testConstructorUsesPreviousException(): void
    {
        $code = '42000';
        $message = 'SQLSTATE[42000]: Syntax error or access violation: 1064 You have an error in your SQL syntax; ' .
            'check the manual that corresponds to your MySQL server version for the right syntax to use near ' .
            "'FRM foo' at line 1";
        $pdoException = new PDOExceptionStub($message, $code);
        $exception = new InvalidQueryException('SELECT * FRM foo', [], $pdoException);
        static::assertStringStartsWith($message, $exception->getMessage());
    }

    public static function dataMessageIncludesQueryAndBind(): array
    {
        return [
            'withBind' => [[
                'one' => sha1(uniqid('one')),
                'two' => sha1(uniqid('two')),
            ]],
            'emptyBind' => [[]],
            'withoutBind' => [null],
        ];
    }

    #[DataProvider('dataMessageIncludesQueryAndBind')]
    public function testMessageIncludesQueryAndBind(?array $bind): void
    {
        $sql = 'SELECT * FRM foo';
        $code = rand(1000, 9999);
        $message = sha1(uniqid('message'));
        $pdoException = new PDOExceptionStub($message, $code);

        $exception = new InvalidQueryException($sql, $bind, $pdoException);

        $expected = $message .
            '; SQL: ' . $sql;
        if ($bind !== null) {
            $expected .= '; Bind: ' . var_export($bind, true);
        }

        static::assertSame($expected, $exception->getMessage());
    }

    public function testGetQuery(): void
    {
        $query = 'SELECT * FRM foo';
        $exception = new InvalidQueryException($query, []);
        static::assertSame($query, $exception->getQuery());
    }

    public function testGetBindData(): void
    {
        $bind = ['foo', 'bar'];
        $exception = new InvalidQueryException('', $bind);
        static::assertSame($bind, $exception->getBindData());
    }

    public function testGetBindDataNotSet(): void
    {
        $exception = new InvalidQueryException('', null);
        static::assertNull($exception->getBindData());
    }

    public function testSuccessfullyDetectsInvalidSyntaxException(): void
    {
        $code = '42000';
        $message = 'SQLSTATE[42000]: Syntax error or access violation: 1064 You have an error in your SQL syntax; ' .
            'check the manual that corresponds to your MySQL server version for the right syntax to use near ' .
            "'FRM foo' at line 1";
        $pdoException = new PDOExceptionStub($message, $code);
        static::assertTrue(InvalidQueryException::isInvalidSyntax($pdoException));
    }

    public function testDetectsNonSyntaxException(): void
    {
        $code = 2002;
        $message = 'SQLSTATE[HY000] [2002] Connection reset by peer';
        $pdoException = new PDOExceptionStub($message, $code);
        static::assertFalse(InvalidQueryException::isInvalidSyntax($pdoException));
    }
}
