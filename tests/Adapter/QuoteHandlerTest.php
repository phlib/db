<?php

declare(strict_types=1);

namespace Phlib\Db\Tests\Adapter;

use Phlib\Db\Adapter\QuoteHandler;
use Phlib\Db\Exception\InvalidArgumentException;
use Phlib\Db\SqlFragment;
use Phlib\Db\SqlStatement;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class QuoteHandlerTest extends TestCase
{
    private QuoteHandler $handler;

    protected function setUp(): void
    {
        $this->handler = new QuoteHandler(function ($value): string {
            return "`{$value}`";
        });
        parent::setUp();
    }

    #[DataProvider('valueDataProvider')]
    public function testValue(mixed $value, string $expected): void
    {
        static::assertSame($expected, $this->handler->value($value));
    }

    public static function valueDataProvider(): array
    {
        $toStringVal = 'foo';
        $object = new SqlFragment($toStringVal);
        return [
            [false, '0'],
            [true, '1'],
            [123, '123'],
            ['1', '1'],
            ['a1', '`a1`'],
            ['1a', '`1a`'],
            [172.16, '172.16'],
            ['172.16', '172.16'],
            ['172.16.255.255', '`172.16.255.255`'],
            ['2017-03-18 00:00:00', '`2017-03-18 00:00:00`'],
            [null, 'NULL'],
            [[1, 2, 3], '1, 2, 3'],
            [['a', 'b', 'c'], '`a`, `b`, `c`'],
            [['a', 'b', []], '`a`, `b`, `Array`'], // contains an array
            [['a', 'b', $object], '`a`, `b`, foo'],
            [$object, $toStringVal],
        ];
    }

    #[DataProvider('intoDataProvider')]
    public function testInto(string $expected, string $text, mixed $value): void
    {
        static::assertSame($expected, $this->handler->into($text, $value));
    }

    public static function intoDataProvider(): array
    {
        return [
            ['field = `value`', 'field = ?', 'value'],
            ['field = 123', 'field = ?', 123],
            ['field IS NULL', 'field IS ?', null],
            ['field IN (1, 2, 3)', 'field IN (?)', [1, 2, 3]],
            ['field IN (`one`, `two`)', 'field IN (?)', ['one', 'two']],
            ['field IN (`one`, `Array`)', 'field IN (?)', ['one', ['two']]],
            ['field = NOW()', 'field = ?', new SqlFragment('NOW()')],
        ];
    }

    #[DataProvider('columnAsData')]
    public function testColumnAs(string $expected, string|array $ident, string $alias, ?bool $auto): void
    {
        $result = ($auto !== null) ?
            $this->handler->columnAs($ident, $alias, $auto) :
            $this->handler->columnAs($ident, $alias);
        static::assertSame($expected, $result);
    }

    public static function columnAsData(): array
    {
        return [
            ['`col1` AS `alias`', 'col1', 'alias', null],
            ['`col1` AS `alias`', 'col1', 'alias', true],
            ['`table1`.`col1` AS `alias`', ['table1', 'col1'], 'alias', true],
            ['`table1`.`col1`.`alias`', ['table1', 'col1', 'alias'], 'alias', true],
        ];
    }

    #[DataProvider('tableAsData')]
    public function testTableAs(string $expected, string|array $ident, string $alias, ?bool $auto): void
    {
        $result = ($auto !== null) ?
            $this->handler->tableAs($ident, $alias, $auto) :
            $this->handler->tableAs($ident, $alias);
        static::assertSame($expected, $result);
    }

    public static function tableAsData(): array
    {
        return [
            ['`table1` AS `alias`', 'table1', 'alias', null],
            ['`table1` AS `alias`', 'table1', 'alias', true],
            ['`schema1`.`table1` AS `alias`', ['schema1', 'table1'], 'alias', true],
        ];
    }

    public function testTableAsWithSqlStatement(): void
    {
        $statement = sha1(uniqid('statement'));
        $alias = sha1(uniqid('alias'));

        $sqlStatement = new class($statement) implements SqlStatement {
            public function __construct(
                private readonly string $statement,
            ) {
            }

            public function __toString(): string
            {
                return $this->statement;
            }
        };

        $actual = $this->handler->tableAs($sqlStatement, $alias);

        // SQL Statement should be wrapped in parentheses
        $expected = "({$statement}) AS `{$alias}`";
        static::assertSame($expected, $actual);
    }

    #[DataProvider('identifierData')]
    public function testIdentifier(string $expected, string|array|SqlFragment $ident, ?bool $auto): void
    {
        $result = ($auto !== null) ?
            $this->handler->identifier($ident, $auto) :
            $this->handler->identifier($ident);
        static::assertSame($expected, $result);
    }

    public static function identifierData(): array
    {
        return [
            ['`col1`', 'col1', null],
            ['`col1`', 'col1', true],
            ['NOW()', new SqlFragment('NOW()'), true],
            ['`col1`.NOW()', ['col1', new SqlFragment('NOW()')], true],
            ['`table1`.`*`', 'table1.*', true],
        ];
    }

    public static function dataIdentifierWithInvalidArrayValue(): array
    {
        return [
            'int' => [rand()],
            'float' => [rand() / 100],
            'bool' => [true],
            'objectNotStringable' => [new \stdClass()],
        ];
    }

    #[DataProvider('dataIdentifierWithInvalidArrayValue')]
    public function testIdentifierWithInvalidArrayValue(mixed $value): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Ident array values must be stringable');

        $this->handler->identifier([$value]);
    }
}
