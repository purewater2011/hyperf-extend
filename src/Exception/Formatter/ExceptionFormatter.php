<?php

declare(strict_types=1);
/**
 * This file is part of hyperf server projects.
 */
namespace Hyperf\Extend\Exception\Formatter;

use Hyperf\ExceptionHandler\Formatter\DefaultFormatter;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Utils\ApplicationContext;
use Hyperf\Utils\Context;
use Hyperf\Extend\Server\HttpServer;
use Hyperf\Extend\Utils\ClientInfoUtil;
use Throwable;

class ExceptionFormatter extends DefaultFormatter
{
    public function format(Throwable $throwable): string
    {
        $message = parent::format($throwable);
        $message .= $this->getMessageForSomeSpecialException($throwable);
        /** @var RequestInterface $request */
        $request = ApplicationContext::getContainer()->get(RequestInterface::class);
        $full_url = $this->getRequestFullUrl($request);
        if (!empty($request) && !empty($full_url)) {
            $request_time = Context::get(HttpServer::CONTEXT_KEY_REQUEST_TIME_FLOAT);
            $seconds_passed = microtime(true) - $request_time;

            $message .= "\nuri is " . $full_url;
            $message .= sprintf("\ncurrent time is %.2f milliseconds after request", $seconds_passed * 1000);
            $message .= "\nremote address is " . ClientInfoUtil::remoteAddress();
            $trace_id = $request->getHeaderLine('x-trace-id') ?: null;
            if (!empty($trace_id)) {
                $message = "{$trace_id} " . $message;
            }
            $caller_chain = $request->getHeaderLine('x-caller-chain') ?: null;
            if (!empty($caller_chain)) {
                $message .= "\ncaller chain is {$caller_chain}";
            }
        }
        return $message;
    }

    private function getRequestFullUrl($request)
    {
        try {
            return $request->fullUrl();
        } catch (Throwable $ignored) {
        }
    }

    private function getMessageForSomeSpecialException(Throwable $throwable)
    {
        $message = '';
        $trace = $throwable->getTrace();
        if ($trace[0]['class'] === 'PDO' && $trace[0]['function'] === '__construct') {
            // 记录下具体的连接失败所对应的 mysql 数据库
            $message .= "\n" . $trace[0]['args'][0];
        }
        return $message;
    }
}
