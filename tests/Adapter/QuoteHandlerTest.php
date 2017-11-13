<?php

namespace Phlib\Db\Tests\Adapter;

use Phlib\Db\Adapter\QuoteHandler;
use Phlib\Db\SqlFragment;

class QuoteHandlerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var QuoteHandler|\PHPUnit_Framework_MockObject_MockObject
     */
    private $handler;

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
     * @dataProvider valueDataProvider
     */
    public function testValue($value, $expected)
    {
        $this->assertEquals($expected, $this->handler->value($value));
    }

    public function valueDataProvider()
    {
        $toStringVal = 'foo';
        $object = new SqlFragment($toStringVal);
        return [
            [false, 0],
            [true, 1],
            [123, 123],
            ['1', 1],
            ['a1', '`a1`'],
            ['1a', '`1a`'],
            [172.16, 172.16],
            ['172.16', 172.16],
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

    /**
     * @param string $expected
     * @param string $text
     * @param mixed $value
     * @dataProvider intoDataProvider
     */
    public function testInto($expected, $text, $value)
    {
        $this->assertEquals($expected, $this->handler->into($text, $value));
    }

    public function intoDataProvider()
    {
        return [
            ["field = `value`", 'field = ?', 'value'],
            ['field = 123', 'field = ?', 123],
            ['field IS NULL', 'field IS ?', null],
            ['field IN (1, 2, 3)', 'field IN (?)', [1, 2, 3]],
            ["field IN (`one`, `two`)", 'field IN (?)', ['one', 'two']],
            ["field IN (`one`, `Array`)", 'field IN (?)', ['one', ['two']]],
            ['field = NOW()', 'field = ?', new SqlFragment('NOW()')]
        ];
    }

    /**
     * @param string $expected
     * @param mixed $ident
     * @param string $alias
     * @param bool $auto
     * @dataProvider columnAsData
     */
    public function testColumnAs($expected, $ident, $alias, $auto)
    {
        $result = (!is_null($auto)) ?
            $this->handler->columnAs($ident, $alias, $auto) :
            $this->handler->columnAs($ident, $alias);
        $this->assertEquals($expected, $result);
    }

    public function columnAsData()
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
     * @dataProvider tableAsData
     */
    public function testTableAs($expected, $ident, $alias, $auto)
    {
        $result = (!is_null($alias)) ? (!is_null($auto)) ?
            $this->handler->tableAs($ident, $alias, $auto) :
            $this->handler->tableAs($ident, $alias) :
            $this->handler->tableAs($ident);
        $this->assertEquals($expected, $result);
    }

    public function tableAsData()
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
     * @dataProvider identifierData
     */
    public function testIdentifier($expected, $ident, $auto)
    {
        $result = (!is_null($auto)) ?
            $this->handler->identifier($ident, $auto) :
            $this->handler->identifier($ident);
        $this->assertEquals($expected, $result);
    }

    public function identifierData()
    {
        return [
            ["`col1`", 'col1', null],
            ["`col1`", 'col1', true],
            ["NOW()", new SqlFragment('NOW()'), true],
            ["`col1`.NOW()", ['col1', new SqlFragment('NOW()')], true],
            ["`table1`.`*`", 'table1.*', true]
        ];
    }
}
