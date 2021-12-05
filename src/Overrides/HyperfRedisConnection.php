<?php

declare(strict_types=1);
/**
 * This file is part of hyperf server projects.
 */
namespace Hyperf\Extend\Overrides;

use Hyperf\Pool\Pool;
use Hyperf\Redis\RedisConnection;
use Hyperf\Utils\ApplicationContext;
use Hyperf\Extend\Events\RedisQueryExecuted;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

class HyperfRedisConnection extends RedisConnection
{
    protected $pool_name;

    public function __construct(ContainerInterface $container, Pool $pool, string $pool_name, array $config)
    {
        $this->pool_name = $pool_name;
        parent::__construct($container, $pool, $config);
    }

    public function reconnect(): bool
    {
        $start_time = microtime(true);
        $result = parent::reconnect();
        $time_used = microtime(true) - $start_time;
        $this->fireReconnectRedisEvent($time_used * 1000, $result);
        return $result;
    }

    private function fireReconnectRedisEvent(float $time_used_in_milliseconds, bool $connect_result)
    {
        $event = new RedisQueryExecuted();
        $event->time = $time_used_in_milliseconds;
        $event->pool = $this->pool_name;
        $event->command = 'reconnect';
        $event->key = $connect_result ? 'success' : 'fail';
        /** @var EventDispatcherInterface $dispatcher */
        $dispatcher = ApplicationContext::getContainer()->get(EventDispatcherInterface::class);
        $dispatcher->dispatch($event);
    }
}
