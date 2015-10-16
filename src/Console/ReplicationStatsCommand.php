<?php

namespace Phlib\Db\Console;

use Phlib\Db\Adapter;
use Phlib\Db\Replication;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;

class ReplicationStatsCommand extends Command
{
    protected function configure()
    {
        $this->setName('replication:stats')
            ->setDescription('CLI for interacting with the Beanstalk server.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $db = new Adapter(['host' => '127.0.0.1']);
        $memcache = new \Memcache();
        (new Replication($db, $memcache))
            ->stats();
    }
}
