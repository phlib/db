<?php

declare(strict_types=1);

namespace Phlib\Db\Adapter;

use Phlib\Db\Exception\RuntimeException;
use Phlib\Db\Exception\UnknownDatabaseException;

class ConnectionFactory
{
    public function __invoke(Config $config): \PDO
    {
        $attempt = 0;
        $maxAttempts = $config->getMaximumAttempts();
        while (++$attempt <= $maxAttempts) {
            try {
                $connection = $this->create($config);
                $setSql = sprintf(
                    'SET NAMES %s, time_zone = "%s"',
                    $config->getCharset(),
                    $config->getTimezone(),
                );
                $connection->exec($setSql);

                return $connection;
            } catch (\PDOException $exception) {
                if (UnknownDatabaseException::isUnknownDatabase($exception)) {
                    throw UnknownDatabaseException::createFromUnknownDatabase($config->getDatabase(), $exception);
                }

                if ($maxAttempts > $attempt) {
                    // sleep with some exponential backoff
                    $msec = (2 ** $attempt) * 50;
                    usleep($msec * 1000);
                } else {
                    // ran out of attempts, throw the last error
                    throw RuntimeException::createFromException($exception);
                }
            }
        }
    }

    public function create(Config $config): \PDO
    {
        return new \PDO(
            $config->getDsn(),
            $config->getUsername(),
            $config->getPassword(),
            $config->getOptions(),
        );
    }
}
