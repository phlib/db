<?php

declare(strict_types=1);

namespace Phlib\Db\Adapter;

use Phlib\Db\Exception\InvalidArgumentException;

class QuoteHandler
{
    /**
     * @param \Closure{
     *   value: mixed,
     * }:string $quoteFn
     */
    public function __construct(
        private readonly \Closure $quoteFn,
        private readonly bool $autoQuoteIdentifiers = true,
    ) {
    }

    /**
     * Quote a value for the database
     */
    public function value(mixed $value): string
    {
        switch (true) {
            case is_object($value):
                if (!method_exists($value, '__toString')) {
                    throw new InvalidArgumentException('Object can not be converted to string value.');
                }
                return (string)$value;
            case is_bool($value):
                return (string)(int)$value;
            case is_numeric($value) && (string)($value + 0) === (string)$value:
                return (string)($value + 0);
            case $value === null:
                return 'NULL';
            case is_array($value):
                $value = array_map(function ($value): string {
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
     * Quote a value to replace the `?` placeholder
     */
    public function into(string $text, mixed $value): string
    {
        return str_replace('?', $this->value($value), $text);
    }

    /**
     * Quote a column identifier and alias
     */
    public function columnAs(
        string|array|object $ident,
        string $alias,
        bool $auto = false,
    ): string {
        return $this->quoteIdentifierAs($ident, $alias, $auto);
    }

    /**
     * Quote a table identifier and alias
     */
    public function tableAs(
        string|array|object $ident,
        string $alias,
        bool $auto = false,
    ): string {
        return $this->quoteIdentifierAs($ident, $alias, $auto);
    }

    /**
     * Quote an identifier
     */
    public function identifier(
        string|array|object $ident,
        bool $auto = false,
    ): string {
        return $this->quoteIdentifierAs($ident, null, $auto);
    }

    private function quoteIdentifierAs(
        string|array|object $ident,
        ?string $alias = null,
        bool $auto = false,
        string $as = ' AS ',
    ): string {
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
                if ($alias !== null && end($ident) === $alias) {
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
            return $q . str_replace("{$q}", "{$q}{$q}", $value) . $q;
        }

        return $value;
    }
}
