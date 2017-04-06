<?php

namespace Phlib\Db\Adapter;

use Phlib\Db\Exception\InvalidArgumentException;

class QuoteHandler
{

    /**
     * @var boolean
     */
    private $autoQuoteIdentifiers = true;

    /**
     * @var callable
     */
    private $quoteFn;

    /**
     * QuoteHandler constructor.
     * @param callable $quoteFn
     * @param bool $autoQuoteIdentifiers
     */
    public function __construct(callable $quoteFn, $autoQuoteIdentifiers = true)
    {
        $this->quoteFn = $quoteFn;
        $this->autoQuoteIdentifiers = $autoQuoteIdentifiers;
    }

    /**
     * Quote a database value.
     *
     * @param string $value
     * @param integer $type
     * @return string
     * @throws InvalidArgumentException
     */
    public function value($value, $type = null)
    {
        switch (true) {
            case is_object($value):
                if (!method_exists($value, '__toString')) {
                    throw new InvalidArgumentException('Object can not be converted to string value.');
                }
                $value = (string)$value;
                break;
            case is_bool($value):
                $value = (int)$value;
                break;
            case (is_numeric($value) && (string)($value + 0) === (string)$value):
                $value = $value + 0;
                break;
            case is_null($value):
                $value = 'NULL';
                break;
            case is_array($value):
                $value = array_map(function ($value) {
                    if (is_array($value)) {
                        $value = 'Array';
                    }
                    return $this->value($value);
                }, $value);
                $value = implode(', ', $value);
                break;
            default:
                $value = call_user_func($this->quoteFn, $value, $type);
        }

        return $value;
    }

    /**
     * Quote into the value for the database.
     *
     * @param string $text
     * @param mixed $value
     * @param string $type
     * @return string
     */
    public function into($text, $value, $type = null)
    {
        return str_replace('?', $this->value($value, $type), $text);
    }

    /**
     * Quote a column identifier and alias.
     *
     * @param string|array $ident
     * @param string $alias
     * @param boolean $auto
     * @return string
     */
    public function columnAs($ident, $alias, $auto = false)
    {
        return $this->quoteIdentifierAs($ident, $alias, $auto);
    }

    /**
     * Quote a table identifier and alias.
     *
     * @param string|array $ident
     * @param string $alias
     * @param boolean $auto
     * @return string
     */
    public function tableAs($ident, $alias = null, $auto = false)
    {
        return $this->quoteIdentifierAs($ident, $alias, $auto);
    }

    /**
     * Quotes an identifier
     *
     * @param string|array $ident
     * @param boolean $auto
     * @return string
     */
    public function identifier($ident, $auto = false)
    {
        return $this->quoteIdentifierAs($ident, null, $auto);
    }

    /**
     * Quote an identifier and an optional alias.
     *
     * @param string|array|object $ident
     * @param string $alias
     * @param boolean $auto
     * @param string $as
     * @return string
     * @throws InvalidArgumentException
     */
    private function quoteIdentifierAs($ident, $alias = null, $auto = false, $as = ' AS ')
    {
        if (is_object($ident) && method_exists($ident, 'assemble')) {
            $quoted = '(' . $ident->assemble() . ')';
        } elseif (is_object($ident)) {
            if (!method_exists($ident, '__toString')) {
                throw new InvalidArgumentException('Object can not be converted to string identifier.');
            }
            $quoted = (string)$ident;
        } else {
            if (is_string($ident)) {
                $ident = explode('.', $ident);
            }
            if (is_array($ident)) {
                $segments = [];
                foreach ($ident as $segment) {
                    if (is_object($segment)) {
                        $segments[] = (string)$segment;
                    } else {
                        $segments[] = $this->performQuoteIdentifier($segment, $auto);
                    }
                }
                if ($alias !== null && end($ident) == $alias) {
                    $alias = null;
                }
                $quoted = implode('.', $segments);
            } else {
                $quoted = $this->performQuoteIdentifier($ident, $auto);
            }
        }

        if ($alias !== null) {
            $quoted .= $as . $this->performQuoteIdentifier($alias, $auto);
        }

        return $quoted;
    }

    /**
     * Quote an identifier.
     *
     * @param string $value
     * @param boolean $auto
     * @return string
     */
    private function performQuoteIdentifier($value, $auto = false)
    {
        if ($auto === false || $this->autoQuoteIdentifiers === true) {
            $q = '`';
            return ($q . str_replace("$q", "$q$q", $value) . $q);
        }

        return $value;
    }
}
