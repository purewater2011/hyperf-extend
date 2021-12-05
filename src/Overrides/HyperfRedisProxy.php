<?php

declare(strict_types=1);
/**
 * This file is part of hyperf server projects.
 */
namespace Hyperf\Extend\Overrides;

use Hyperf\Redis\RedisProxy;
use Hyperf\Utils\ApplicationContext;
use Hyperf\Utils\Context;
use Hyperf\Extend\Events\RedisQueryExecuted;
use Psr\EventDispatcher\EventDispatcherInterface;

class HyperfRedisProxy extends RedisProxy
{
    public function __call($name, $arguments)
    {
        $start_time = microtime(true);
        $result = parent::__call($name, $arguments);
        $time_used = microtime(true) - $start_time;
        $this->fireRedisEvent($time_used * 1000, $name, $arguments);
        return $result;
    }

    private function getMultiStartTime()
    {
        return Context::get(__CLASS__ . ':multi:start:time:' . $this->poolName, 0);
    }

    private function markMultiStart(float $microtime)
    {
        Context::set(__CLASS__ . ':multi:start:time:' . $this->poolName, $microtime);
    }

    private function clearMultiStartTime()
    {
        Context::destroy(__CLASS__ . ':multi:start:time:' . $this->poolName);
    }

    private function getMultiCommandCount()
    {
        $context_key = __CLASS__ . ':multi:command:count:' . $this->poolName;
        return Context::get($context_key, 0);
    }

    private function clearMultiCommandCount()
    {
        Context::destroy(__CLASS__ . ':multi:command:count:' . $this->poolName);
    }

    private function incrMultiCommandCount()
    {
        $context_key = __CLASS__ . ':multi:command:count:' . $this->poolName;
        Context::set($context_key, Context::get($context_key, 0) + 1);
    }

    private function fireRedisEvent($time_used_in_milliseconds, $name, $arguments)
    {
        $multi_start_time = $this->getMultiStartTime();
        $event = new RedisQueryExecuted();
        $event->time = $time_used_in_milliseconds;
        $event->pool = $this->poolName;
        if ($name === 'rawCommand') {
            $name = $arguments[0];
            $event->key = $arguments[1];
        }
        if ($name === 'multi') {
            // multi 命令在执行 exec 的时候才抛出事件
            $this->markMultiStart(microtime(true) - $time_used_in_milliseconds / 1000);
            return;
        }
        if ($name === 'exec') {
            $event->key = 'multi-' . $this->getMultiCommandCount();
            $event->time = (microtime(true) - $multi_start_time) * 1000;
            $multi_start_time = 0;
            $this->clearMultiCommandCount();
            $this->clearMultiStartTime();
        }
        if ($multi_start_time > 0) {
            // multi 命令在执行 exec 的时候才抛出事件
            $this->incrMultiCommandCount();
            return;
        }
        $event->command = $event->command ?: $name;
        if (empty($event->key)) {
            $event->key = $arguments[0] ?? null;
        }
        /** @var EventDispatcherInterface $dispatcher */
        $dispatcher = ApplicationContext::getContainer()->get(EventDispatcherInterface::class);
        $dispatcher->dispatch($event);
    }
}
