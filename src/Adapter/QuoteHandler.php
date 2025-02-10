<?php

declare(strict_types=1);

namespace Phlib\Db\Adapter;

use Phlib\Db\Exception\InvalidArgumentException;
use Phlib\Db\SqlStatement;

class QuoteHandler
{
    /**
     * @param \Closure{
     *   value: string,
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
    public function value(
        string|\Stringable|array|bool|int|float|null $value,
    ): string {
        switch (true) {
            case $value instanceof \Stringable:
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
            case is_string($value):
                return ($this->quoteFn)($value);
            default:
                // Not reachable due to type declarations
                throw new InvalidArgumentException('Value can not be converted to string for quoting');
        }
    }

    /**
     * Quote a value to replace the `?` placeholder
     */
    public function into(
        string $text,
        string|\Stringable|array|bool|int|float|null $value,
    ): string {
        return str_replace('?', $this->value($value), $text);
    }

    /**
     * Quote a column identifier and alias
     */
    public function columnAs(
        string|\Stringable|array $ident,
        string $alias,
        bool $auto = false,
    ): string {
        return $this->quoteIdentifierAs($ident, $alias, $auto);
    }

    /**
     * Quote a table identifier and alias
     */
    public function tableAs(
        string|\Stringable|array $ident,
        string $alias,
        bool $auto = false,
    ): string {
        return $this->quoteIdentifierAs($ident, $alias, $auto);
    }

    /**
     * Quote an identifier
     */
    public function identifier(
        string|\Stringable|array $ident,
        bool $auto = false,
    ): string {
        return $this->quoteIdentifierAs($ident, null, $auto);
    }

    private function quoteIdentifierAs(
        string|\Stringable|array $ident,
        ?string $alias = null,
        bool $auto = false,
        string $as = ' AS ',
    ): string {
        if ($ident instanceof \Stringable) {
            $quoted = (string)$ident;
            if ($ident instanceof SqlStatement) {
                $quoted = '(' . $quoted . ')';
            }
        } else {
            if (is_string($ident)) {
                $ident = explode('.', $ident);
            }

            $segments = [];
            foreach ($ident as $segment) {
                if ($segment instanceof \Stringable) {
                    $segments[] = (string)$segment;
                } elseif (is_string($segment)) {
                    $segments[] = $this->performQuoteIdentifier($segment, $auto);
                } else {
                    throw new InvalidArgumentException('Ident array values must be stringable');
                }
            }
            if ($alias !== null && end($ident) === $alias) {
                $alias = null;
            }
            $quoted = implode('.', $segments);
        }

        if ($alias !== null) {
            $quoted .= $as . $this->performQuoteIdentifier($alias, $auto);
        }

        return $quoted;
    }

    private function performQuoteIdentifier(
        string $value,
        bool $auto = false,
    ): string {
        if ($auto === false || $this->autoQuoteIdentifiers === true) {
            $q = '`';
            return $q . str_replace("{$q}", "{$q}{$q}", $value) . $q;
        }

        return $value;
    }
}
