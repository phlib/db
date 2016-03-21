<?php

namespace Phlib\Db\Helper;

use Phlib\Db\Adapter;
use Phlib\Db\Exception;

class Replication
{
    /**
     * @var Adapter
     */
    protected $master;

    /**
     * @var \Phlib\Db\Adapter[]
     */
    protected $slaves;

    /**
     * @var Replication\StorageInterface $storage
     */
    protected $storage;

    /**
     * @var int
     */
    protected $weighting = 100;

    /**
     * @var int
     */
    protected $maxSleep = 1000; // ms

    /**
     * @var int
     */
    protected $loadValue = 0;

    /**
     * @var int
     */
    protected $loadUpdated = 0;

    /**
     * @var int
     */
    protected $updateInterval = 1;

    /**
     * Constructor
     *
     * @param Adapter $master
     * @param Adapter[] $slaves
     * @param Replication\StorageInterface $storage
     */
    public function __construct(Adapter $master, array $slaves, Replication\StorageInterface $storage)
    {
        $this->master  = $master;
        $this->slaves  = $slaves;
        $this->host    = $master->getConfig()['host'];
        $this->storage = $storage;

        foreach ($slaves as $slave) {
            if (!$slave instanceof Adapter) {
                throw new Exception\InvalidArgumentException('Specified slave is not an expected adapter');
            }
        }
    }

    /**
     * @param array $config
     * @return self
     */
    public static function createFromConfig(array $config)
    {
        $master = new Adapter([]);
        $slaves = [];
        foreach ($config['slaves'] as $slave) {
            $slaves[] = new Adapter($slave);
        }
        $storageClass = $config['storage']['class'];
        if (!class_exists($storageClass)) {
            throw new Exception\InvalidArgumentException;
        }
        if (!method_exists([$storageClass, 'createFromConfig'])) {
            throw new Exception\InvalidArgumentException;
        }
        $storage = call_user_func_array([$storageClass, 'createFromConfig'], $config['storage']['args']);
        return new static($master, $slaves, $storage);
    }

    /**
     * Get throttle weighting
     *
     * @return int
     */
    public function getWeighting()
    {
        return $this->weighting;
    }

    /**
     * Set throttle weighting
     *
     * @param int $weighting
     * @return $this
     */
    public function setWeighting($weighting)
    {
        $this->weighting = (int)$weighting;
        return $this;
    }

    /**
     * Get the maximum number of milliseconds the throttle can sleep for.
     *
     * @return int
     */
    public function getMaximumSleep()
    {
        return $this->maxSleep;
    }

    /**
     * Set the maximum number of milliseconds the throttle can sleep for.
     *
     * @param int $milliseconds
     * @return $this
     */
    public function setMaximumSleep($milliseconds)
    {
        $this->maxSleep = (int)$milliseconds;
        return $this;
    }

    /**
     * @return $this
     */
    public function monitor()
    {
        $maxBehind = 0;
        foreach ($this->slaves as $slave) {
            $status    = $this->fetchStatus($slave);
            $maxBehind = max($status['Seconds_Behind_Master'], $maxBehind);
        }

        // append data point to the history for this host
        $history   = $this->storage->getHistory($this->host);
        $history[] = $maxBehind;
        if (count($history) > $this->capturePoints) {
            // trim the history
            array_shift($history);
        }

        // calculate the average
        $avgBehind     = 0;
        $historyLength = count($history);
        if ($historyLength > 0) {
            $avgBehind = ceil(array_sum($history) / $historyLength);
        }

        $this->storage->setSecondsBehind($this->host, $avgBehind);
        $this->storage->setHistory($this->host, $history);

        return $this;
    }

    /**
     * @return array
     */
    public function stats()
    {
        return [];
    }

    /**
     * @param Adapter $slave
     * @return array
     */
    public function fetchStatus(Adapter $slave)
    {
        $status = $slave->query('SHOW SLAVE STATUS')
            ->fetch(\PDO::FETCH_ASSOC);
        if (!array_key_exists('Seconds_Behind_Master', $status) or is_null($status['Seconds_Behind_Master'])) {
            throw new Exception\RuntimeException('Seconds_Behind_Master is null');
        }
        return $status;
    }

    /**
     * Throttle
     *
     * Updates the stored load value if out-of-date, and sleeps for the required
     * time if throttling is enabled.
     *
     * @return $this
     */
    public function throttle()
    {
        $currentInterval = time() - $this->loadUpdated;
        if ($currentInterval > $this->updateInterval) {
            $this->loadValue   = (int)$this->storage->getSecondsBehind($this->host);
            $this->loadUpdated = time();
        }
        $this->sleep();

        return $this;
    }

    /**
     * Sleep for stored sleep time
     *
     * @return $this
     */
    protected function sleep()
    {
        $alteredLoad = pow($this->loadValue, 5.2) / 100;
        $weighting   = $this->weighting / 100;
        $sleepMs     = min($alteredLoad * $weighting, $this->maxSleep);

        usleep(floor($sleepMs * 1000));

        return $this;
    }
}