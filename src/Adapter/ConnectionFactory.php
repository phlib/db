<?php

namespace Phlib\Db\Adapter;

use Phlib\Db\Exception\RuntimeException;
use Phlib\Db\Exception\UnknownDatabaseException;

class ConnectionFactory
{
    /**
     * @param Config $config
     * @return \PDO
     * @throws UnknownDatabaseException
     * @throws RuntimeException
     */
    public function __invoke(Config $config)
    {
        $dsn      = $config->getDsn();
        $username = $config->getUsername();
        $password = $config->getPassword();
        $options  = $config->getOptions();

        $attempt     = 0;
        $maxAttempts = $config->getMaximumAttempts();
        while (++$attempt <= $maxAttempts) {
            try {
                $connection = new \PDO($dsn, $username, $password, $options);
                $statement  = $connection->prepare('SET NAMES ?, time_zone = ?');
                $statement->execute([$config->getCharset(), $config->getTimezone()]);

                return $connection;

            } catch (\PDOException $exception) {
                if (UnknownDatabaseException::isUnknownDatabase($exception)) {
                    throw UnknownDatabaseException::create($config->getDatabase(), $exception);
                }

                if ($maxAttempts > $attempt) {
                    // more tries left, so we'll log this error
                    $template = 'Failed connection to "%s" on attempt %d with error "%s"';
                    error_log(sprintf($template, $dsn, $attempt, $exception->getMessage()));

                    // sleep with some exponential backoff
                    $msec = pow(2, $attempt) * 50;
                    usleep($msec * 1000);
                } else {
                    // ran out of attempts, throw the last error
                    throw RuntimeException::create($exception);
                }
            }
        }
    }
}
