<?php

declare(strict_types=1);
/**
 * This file is part of hyperf server projects.
 */
namespace Hyperf\Extend\Listener;

use Hyperf\Command\Event\FailToHandle;
use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\Extend\Utils\LogUtil;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Event\ConsoleCommandEvent;

class ConsoleCommandEventListener implements ListenerInterface
{
    private static $running_command;

    public function listen(): array
    {
        return [
            ConsoleCommandEvent::class,
            FailToHandle::class,
        ];
    }

    public function process(object $event)
    {
        if ($event instanceof ConsoleCommandEvent) {
            self::$running_command = $event->getCommand();
        } elseif ($event instanceof FailToHandle) {
            $exception = $event->getThrowable();
            if ($exception instanceof \GuzzleHttp\Exception\ConnectException) {
                $log_message = 'failed to request url ' . $exception->getRequest()->getUri();
                $log_message .= ' with error context ' . json_encode($exception->getHandlerContext());
                LogUtil::stdout()->error($log_message);
            }
            throw $exception;
        }
    }

    public static function getRunningCommand()
    {
        return self::$running_command;
    }

    public static function setRunningCommand(?Command $command)
    {
        self::$running_command = $command;
    }
}
