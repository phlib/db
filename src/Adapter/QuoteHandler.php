<?php

declare(strict_types=1);

namespace Phlib\Db\Adapter;

use Phlib\Db\Exception\InvalidArgumentException;

class QuoteHandler
{
    private bool $autoQuoteIdentifiers;

    private \Closure $quoteFn;

    /**
     * @param \Closure $quoteFn {
     *   @var mixed $value
     *   @return string
     * }
     */
    public function __construct(\Closure $quoteFn, bool $autoQuoteIdentifiers = true)
    {
        $this->quoteFn = $quoteFn;
        $this->autoQuoteIdentifiers = $autoQuoteIdentifiers;
    }

    /**
     * Quote a database value.
     *
     * @param mixed $value
     */
    public function value($value): string
    {
        switch (true) {
            case is_object($value):
                if (!method_exists($value, '__toString')) {
                    throw new InvalidArgumentException('Object can not be converted to string value.');
                }
                return (string)$value;
            case is_bool($value):
                return (string)(int)$value;
            case (is_numeric($value) && (string)($value + 0) === (string)$value):
                return (string)($value + 0);
            case $value === null:
                return 'NULL';
            case is_array($value):
                $value = array_map(function ($value) {
                    if (is_array($value)) {
                        $value = 'Array';
                    }
                    return $this->value($value);
                }, $value);
                return implode(', ', $value);
            default:
                return ($this->quoteFn)($value);
        }
    }

    /**
     * Quote into the value for the database.
     *
     * @param mixed $value
     */
    public function into(string $text, $value): string
    {
        return str_replace('?', $this->value($value), $text);
    }

    /**
     * Quote a column identifier and alias.
     *
     * @param string|string[] $ident
     */
    public function columnAs($ident, string $alias, bool $auto = false): string
    {
        return $this->quoteIdentifierAs($ident, $alias, $auto);
    }

    /**
     * Quote a table identifier and alias.
     *
     * @param string|string[] $ident
     */
    public function tableAs($ident, string $alias, bool $auto = false): string
    {
        return $this->quoteIdentifierAs($ident, $alias, $auto);
    }

    /**
     * Quotes an identifier
     *
     * @param string|string[] $ident
     */
    public function identifier($ident, bool $auto = false): string
    {
        return $this->quoteIdentifierAs($ident, null, $auto);
    }

    /**
     * Quote an identifier and an optional alias.
     *
     * @param string|array|object $ident
     */
    private function quoteIdentifierAs($ident, string $alias = null, bool $auto = false, string $as = ' AS '): string
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

    private function performQuoteIdentifier(string $value, bool $auto = false): string
    {
        if ($auto === false || $this->autoQuoteIdentifiers === true) {
            $q = '`';
            return ($q . str_replace("{$q}", "{$q}{$q}", $value) . $q);
        }

        return $value;
    }
}
