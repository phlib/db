<?php

namespace Phlib\Db\Tests\Adapter;

use Phlib\Db\Adapter\QuoteHandler;
use Phlib\Db\Tests\ToStringClass;

class QuoteHandlerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var QuoteHandler|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $handler;

    public function setUp()
    {
        $this->handler = new QuoteHandler(function ($value) {
            return "`$value`";
        });
        parent::setUp();
    }

    /**
     * @param mixed $value
     * @param mixed $expected
     * @dataProvider quoteDataProvider
     */
    public function testQuote($value, $expected)
    {
        $this->assertEquals($expected, $this->handler->quote($value));
    }

    public function quoteDataProvider()
    {
        $toStringVal = 'foo';
        $object = new ToStringClass($toStringVal);
        return [
            [false, 0],
            [true, 1],
            [123, 123],
            ['1', 1],
            ['a1', '`a1`'],
            ['1a', '`1a`'],
            [null, 'NULL'],
            [[1, 2, 3], '1, 2, 3'],
            [['a', 'b', 'c'], '`a`, `b`, `c`'],
            [['a', 'b', []], '`a`, `b`, `Array`'], // contains an array
            [['a', 'b', $object], '`a`, `b`, foo'],
            [$object, $toStringVal],
        ];
    }

    /**
     * @param string $expected
     * @param string $text
     * @param mixed $value
     * @dataProvider quoteIntoDataProvider
     */
    public function testQuoteInto($expected, $text, $value)
    {
        $this->assertEquals($expected, $this->handler->quoteInto($text, $value));
    }

    public function quoteIntoDataProvider()
    {
        return [
            ["field = `value`", 'field = ?', 'value'],
            ['field = 123', 'field = ?', 123],
            ['field IS NULL', 'field IS ?', null],
            ['field IN (1, 2, 3)', 'field IN (?)', [1, 2, 3]],
            ["field IN (`one`, `two`)", 'field IN (?)', ['one', 'two']],
            ["field IN (`one`, `Array`)", 'field IN (?)', ['one', ['two']]],
            ['field = NOW()', 'field = ?', new ToStringClass('NOW()')]
        ];
    }

    /**
     * @param string $expected
     * @param mixed $ident
     * @param string $alias
     * @param bool $auto
     * @dataProvider quoteColumnAsData
     */
    public function testQuoteColumnAs($expected, $ident, $alias, $auto)
    {
        $result = (!is_null($auto)) ?
            $this->handler->quoteColumnAs($ident, $alias, $auto) :
            $this->handler->quoteColumnAs($ident, $alias);
        $this->assertEquals($expected, $result);
    }

    public function quoteColumnAsData()
    {
        return [
            ["`col1`", 'col1', null, null],
            ["`col1` AS `alias`", 'col1', 'alias', null],
            ["`col1` AS `alias`", 'col1', 'alias', true],
            ["`table1`.`col1`", ['table1', 'col1'], null, true],
            ["`table1`.`col1`.`alias`", ['table1', 'col1', 'alias'], 'alias', true]
        ];
    }

    /**
     * @param string $expected
     * @param string $ident
     * @param string $alias
     * @param bool $auto
     * @dataProvider quoteTableAsData
     */
    public function testQuoteTableAs($expected, $ident, $alias, $auto)
    {
        $result = (!is_null($alias)) ? (!is_null($auto)) ?
            $this->handler->quoteTableAs($ident, $alias, $auto) :
            $this->handler->quoteTableAs($ident, $alias) :
            $this->handler->quoteTableAs($ident);
        $this->assertEquals($expected, $result);
    }

    public function quoteTableAsData()
    {
        return [
            ["`table1`", 'table1', null, null],
            ["`table1` AS `alias`", 'table1', 'alias', null],
            ["`table1` AS `alias`", 'table1', 'alias', true],
        ];
    }

    /**
     * @param string $expected
     * @param string $ident
     * @param bool $auto
     * @dataProvider quoteIdentifierData
     */
    public function testQuoteIdentifier($expected, $ident, $auto)
    {
        $result = (!is_null($auto)) ?
            $this->handler->quoteIdentifier($ident, $auto) :
            $this->handler->quoteIdentifier($ident);
        $this->assertEquals($expected, $result);
    }

    public function quoteIdentifierData()
    {
        return [
            ["`col1`", 'col1', null],
            ["`col1`", 'col1', true],
            ["NOW()", new ToStringClass('NOW()'), true],
            ["`col1`.NOW()", ['col1', new ToStringClass('NOW()')], true],
            ["`table1`.`*`", 'table1.*', true]
        ];
    }
}
