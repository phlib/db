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
        $attempt     = 0;
        $maxAttempts = $config->getMaximumAttempts();
        while (++$attempt <= $maxAttempts) {
            try {
                $connection = $this->create($config);
                $connection->prepare('SET NAMES ?, time_zone = ?')
                    ->execute([$config->getCharset(), $config->getTimezone()]);

                return $connection;
            } catch (\PDOException $exception) {
                if (UnknownDatabaseException::isUnknownDatabase($exception)) {
                    throw UnknownDatabaseException::create($config->getDatabase(), $exception);
                }

                if ($maxAttempts > $attempt) {
                    // more tries left, so we'll log this error
//                    $template = 'Failed connection to "%s" on attempt %d with error "%s"';
//                    $dsn      = $config->getDsn();
//                    error_log(sprintf($template, $dsn, $attempt, $exception->getMessage()));
//                    $logger->error(sprintf($template, $dsn, $attempt, $exception->getMessage()), [
//                        'e_message' => $exception->getMessage(),
//                        'e_code'    => $exception->getCode(),
//                        'e_file'    => $exception->getFile(),
//                        'e_line'    => $exception->getLine(),
//                        'e_trace'   => $exception->getTraceAsString()
//                    ]);

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

    /**
     * @param Config $config
     * @return \PDO
     */
    public function create(Config $config)
    {
        return new \PDO(
            $config->getDsn(),
            $config->getUsername(),
            $config->getPassword(),
            $config->getOptions()
        );
    }
}
