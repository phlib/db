<?php

namespace Phlib\Db\Adapter;

interface QuoteableInterface
{
    /**
     * @param mixed $value
     * @param int $type
     * @return string
     */
    public function quote($value, $type = null);

    /**
     * @param string $text
     * @param mixed $value
     * @param int $type
     * @return string
     */
    public function quoteInto($text, $value, $type = null);

    /**
     * @param string $ident
     * @param string $alias
     * @param bool $auto
     * @return string
     */
    public function quoteColumnAs($ident, $alias, $auto = false);

    /**
     * @param string $ident
     * @param string  $alias
     * @param bool $auto
     * @return string
     */
    public function quoteTableAs($ident, $alias = null, $auto = false);

    /**
     * @param $ident
     * @param bool $auto
     * @return string
     */
    public function quoteIdentifier($ident, $auto = false);
}
