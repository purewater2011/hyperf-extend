<?php

declare(strict_types=1);
/**
 * This file is part of hyperf server projects.
 */
namespace Hyperf\Extend\Events;

class RedisQueryExecuted
{
    /**
     * The number of milliseconds it took to execute the redis query.
     * @var float
     */
    public $time;

    public $pool;

    public $command;

    public $key;
}
