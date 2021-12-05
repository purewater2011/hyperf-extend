<?php

declare(strict_types=1);
/**
 * This file is part of hyperf server projects.
 */
namespace Hyperf\Extend\Listener;

use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\Framework\Event\OnWorkerExit;
use Hyperf\Framework\Event\OnWorkerStop;
use Swoole\Timer;

class OnWorkerExitListener implements ListenerInterface
{
    public function listen(): array
    {
        return [
            OnWorkerExit::class,
            OnWorkerStop::class,
        ];
    }

    public function process(object $event)
    {
        Timer::clearAll();
    }
}
