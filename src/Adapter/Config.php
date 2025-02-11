<?php

declare(strict_types=1);

namespace Phlib\Db\Adapter;

use Phlib\Db\Exception\InvalidArgumentException;

class Config
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config + [
            'charset' => 'utf8mb4',
            'timezone' => '+0:00',
        ];
    }

    public function getDsn(): string
    {
        if (!isset($this->config['host'])) {
            throw new InvalidArgumentException('Missing host config param');
        }

        $dsn = "mysql:host={$this->config['host']}";
        if (isset($this->config['port'])) {
            $dsn .= ";port={$this->config['port']}";
        }
        if (isset($this->config['dbname'])) {
            $dsn .= ";dbname={$this->config['dbname']}";
        }
        return $dsn;
    }

    public function getUsername(): string
    {
        return $this->config['username'] ?? '';
    }

    public function getPassword(): string
    {
        return $this->config['password'] ?? '';
    }

    public function getOptions(): array
    {
        $timeoutValue = $this->config['timeout'] ?? '';
        $timeoutOptions = [
            'options' => [
                'min_range' => 0,
                'max_range' => 120,
                'default' => 2,
            ],
        ];
        $timeout = filter_var($timeoutValue, FILTER_VALIDATE_INT, $timeoutOptions);
        $driverAttributes = $this->config['attributes'] ?? [];
        return $driverAttributes + [
            \PDO::ATTR_TIMEOUT => $timeout,
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES => 0,
        ];
    }

    public function getDatabase(): string
    {
        return $this->config['dbname'] ?? '';
    }

    public function setDatabase(string $name): self
    {
        $this->config['dbname'] = $name;
        return $this;
    }

    public function getCharset(): string
    {
        return $this->config['charset'] ?? 'utf8mb4';
    }

    public function setCharset(string $value): self
    {
        $this->config['charset'] = $value;
        return $this;
    }

    public function getTimezone(): string
    {
        return $this->config['timezone'] ?? '+0:00';
    }

    public function setTimezone(string $timezone): self
    {
        $this->config['timezone'] = $timezone;
        return $this;
    }

    public function getMaximumAttempts(): int
    {
        $retryValue = $this->config['retryCount'] ?? 0;
        $retryOptions = [
            'options' => [
                'min_range' => 0,
                'max_range' => 10,
                'default' => 0,
            ],
        ];
        $retryCount = filter_var($retryValue, FILTER_VALIDATE_INT, $retryOptions);
        return $retryCount + 1;
    }

    public function toArray(): array
    {
        return $this->config;
    }
}
