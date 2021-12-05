<?php

declare(strict_types=1);
/**
 * This file is part of hyperf server projects.
 */
namespace Hyperf\Extend\Utils;

use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\ExceptionHandler\Formatter\FormatterInterface;
use Hyperf\Logger\LoggerFactory;
use Hyperf\Utils\ApplicationContext;
use Hyperf\Extend\Compatibility\HttpRequest;
use Psr\Log\LoggerInterface;
use Throwable;

class LogUtil
{
    public static function stdout(): StdoutLoggerInterface
    {
        return ApplicationContext::getContainer()->get(StdoutLoggerInterface::class);
    }

    public static function logger(string $name, $group = 'default'): LoggerInterface
    {
        return ApplicationContext::getContainer()->get(LoggerFactory::class)->get($name, $group);
    }

    public static function logThrowable(Throwable $e, ?LoggerInterface $logger = null)
    {
        if (!$logger) {
            // 开发环境下打印至终端，线上环境输出至日志文件
            $logger = ENVUtil::isDev() ? self::stdout() : self::logger('exception-handler');
        }
        $logger->error(ApplicationContext::getContainer()->get(FormatterInterface::class)->format($e));
    }

    public static function writeLoggerMessage($message, $name = 'hyperf', $method = 'info')
    {
        $logger = self::logger($name);
        $trace_id = Util::getTraceId();
        if (!empty($trace_id)) {
            $message = $trace_id . ' ' . $message;
            $request = HttpRequest::current();
            if ($request) {
                $message .= "\n" . $request->fullUrl();
            }
        }
        $logger->{$method}($message);
    }

    public static function logError(string $message, $name = 'hyperf')
    {
        self::writeLoggerMessage($message, $name, 'error');
    }

    public static function logDebug(string $message, $name = 'hyperf')
    {
        self::writeLoggerMessage($message, $name, 'debug');
    }
}
