<?php

namespace app\components\queue\adapters;

use Pheanstalk\Contract\PheanstalkInterface;
use Pheanstalk\Pheanstalk;
use Throwable;
use Xiusin\PhalconQueue\AbstractAdapter;

class BeanstalkdQueue extends AbstractAdapter
{
    protected $connection;

    public function __construct(array $config)
    {
        parent::__construct($config);

        $this->connection = Pheanstalk::create(
            $config['host'] ?? '127.0.0.1',
            $config['port'] ?? null,
            $config['connect_timeout'] ?? 30,
        );
    }

    public function consume(string $queue)
    {
        while ($job = $this->connection->watchOnly($queue)->reserve()) {
            $payload = null;
            try {
                $payload = unserialize($job->getData());
                $payload->handle();
                $this->connection->delete($job);
            } catch (Throwable $e) {
                $this->connection->release($job);
                $this->pushFailedJob($payload, $e);
            }
        }
    }

    public function send(Job $job)
    {
        $this->connection->useTube($job->getQueueName())->put(
            serialize($job),
            PheanstalkInterface::DEFAULT_PRIORITY,
            $job->getDelay(),
            0
        );
    }
}