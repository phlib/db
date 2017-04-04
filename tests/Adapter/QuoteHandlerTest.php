<?php

namespace Phlib\Db\Tests\Adapter;

use Phlib\Db\Adapter\QuotableInterface;
use Phlib\Db\Adapter\QuoteHandler;
use Phlib\Db\Tests\ToStringClass;

class QuoteHandlerTest extends \PHPUnit_Framework_TestCase
{
    protected $handler;

    public function setUp()
    {
        $this->handler = new QuoteHandler(function ($value) {
            return "`$value`";
        });
        parent::setUp();
    }

    public function testImplementInterface()
    {
        $this->assertInstanceOf(QuotableInterface::class, $this->handler);
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
        return array(
            array("field = `value`", 'field = ?', 'value'),
            array('field = 123', 'field = ?', 123),
            array('field IS NULL', 'field IS ?', null),
            array('field IN (1, 2, 3)', 'field IN (?)', array(1,2,3)),
            array("field IN (`one`, `two`)", 'field IN (?)', array('one', 'two')),
            array("field IN (`one`, `Array`)", 'field IN (?)', array('one', array('two'))),
            array('field = NOW()', 'field = ?', new ToStringClass('NOW()'))
        );
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
        return array(
            array("`col1`", 'col1', null, null),
            array("`col1` AS `alias`", 'col1', 'alias', null),
            array("`col1` AS `alias`", 'col1', 'alias', true),
            array("`table1`.`col1`", array('table1', 'col1'), null, true),
            array("`table1`.`col1`.`alias`", array('table1', 'col1', 'alias'), 'alias', true)
        );
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
        return array(
            array("`table1`", 'table1', null, null),
            array("`table1` AS `alias`", 'table1', 'alias', null),
            array("`table1` AS `alias`", 'table1', 'alias', true),
        );
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
        return array(
            array("`col1`", 'col1', null),
            array("`col1`", 'col1', true),
            array("NOW()", new ToStringClass('NOW()'), true),
            array("`col1`.NOW()", array('col1', new ToStringClass('NOW()')), true),
            array("`table1`.`*`", 'table1.*', true)
        );
    }
}
