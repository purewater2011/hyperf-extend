<?php

declare(strict_types=1);
/**
 * This file is part of hyperf server projects.
 */
namespace Hyperf\Extend\Overrides;

use Hyperf\Contract\ConnectionInterface;
use Hyperf\Redis\Pool\RedisPool;
use Hyperf\Redis\RedisConnection;

class HyperfRedisPool extends RedisPool
{
    protected function createConnection(): ConnectionInterface
    {
        return new RedisConnection($this->container, $this, $this->config);
    }
}
