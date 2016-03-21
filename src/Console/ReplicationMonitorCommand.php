<?php

namespace Phlib\Db\Console;

use Phlib\Db\Replication;
use Phlib\ConsoleProcess\Command\DaemonCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\StreamOutput;

class ReplicationMonitorCommand extends DaemonCommand
{
    /**
     * @var Replication
     */
    protected $replication;

    protected function configure()
    {
        $this->setName('replication:monitor')
            ->setDescription('CLI for monitoring MySQL slave status.');
    }

    protected function onAfterDaemonizeChild(InputInterface $input, OutputInterface $output)
    {
        $this->replication = $this->getReplication();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->replication->monitor();
    }

    protected function getReplication()
    {
        $config = $this->getHelper('configuration')->fetch();
        return Replication::createFromConfig($config);
    }

    protected function createChildOutput()
    {
        $filename = getcwd() . '/replication-monitor.log';
        return new StreamOutput(fopen($filename, 'a'));
    }
}
