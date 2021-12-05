<?php

declare(strict_types=1);
/**
 * This file is part of hyperf server projects.
 */
namespace Hyperf\Extend\Listener;

use Hyperf\Database\Events\QueryExecuted;
use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\Logger\LoggerFactory;
use Hyperf\Utils\Context;
use Hyperf\Extend\Command\MultiActionBaseCommand;
use Hyperf\Extend\Compatibility\HttpRequest;
use Hyperf\Extend\Events\HttpRequestCompleted;
use Hyperf\Extend\Events\RedisQueryExecuted;
use Hyperf\Extend\Server\Events\ResponseSendFailed;
use Hyperf\Extend\Server\Events\SlowRequest;
use Hyperf\Extend\Utils\ENV;
use Psr\Container\ContainerInterface;

/**
 * 用于慢请求或者请求响应失败日志记录.
 */
class ResponseSendFailedOrSlowRequestListener implements ListenerInterface
{
    /**
     * @var ContainerInterface
     */
    private $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function listen(): array
    {
        return [
            ResponseSendFailed::class,
            SlowRequest::class,

            QueryExecuted::class,
            RedisQueryExecuted::class,
            HttpRequestCompleted::class,
        ];
    }

    /**
     * @param object|QueryExecuted|SlowRequest $event
     */
    public function process(object $event)
    {
        $running_command = ENV::getRunningCommand();
        if (!empty($running_command) && $running_command instanceof MultiActionBaseCommand) {
            // 命令行执行模式下，不开启慢请求追踪
            return;
        }
        if (empty(HttpRequest::current())) {
            // 没有在接口请求执行环境下，不做事件追踪记录
            return;
        }
        $context_key = __CLASS__ . ':events';
        $event_items = Context::get($context_key, []);
        if ($event instanceof ResponseSendFailed || $event instanceof SlowRequest) {
            $request_time = $event->request_time;
            $request_time_millisecond_part = sprintf('%03d', ($request_time - floor($request_time)) * 1000);
            $message = $event->trace_id . ' ';
            if ($event instanceof ResponseSendFailed) {
                $logger = $this->container->get(LoggerFactory::class)->get('response-fail');
                $message .= sprintf("response send failed after %.3f seconds\n", $event->fail_time - $request_time);
            } elseif ($event instanceof SlowRequest) {
                $logger = $this->container->get(LoggerFactory::class)->get('slow-request');
                $message .= sprintf("slow request after %.3f seconds\n", $event->end_time - $request_time);
            }
            $message .= "uri is {$event->uri}\n";
            $message .= 'request time is ' . date('Y-m-d H:i:s', intval($request_time)) . ".{$request_time_millisecond_part}\n";
            $message .= "remote address is {$event->remote_addr}\n";
            if (!empty($event->caller_chain)) {
                $message .= "caller chain is {$event->caller_chain}\n";
            }
            $message .= $this->getMessageOfEventItems($event_items, $request_time);
            $message .= "\n";
            $logger->info($message);
        } else {
            $event_items[] = [microtime(true), $event];
            Context::set($context_key, $event_items);
        }
    }

    private function getMessageOfEventItems(array $event_items, float $request_time): string
    {
        $message = '';
        foreach ($event_items as $event_item) {
            $event_time_after_request = $event_item[0] - $request_time;
            $event = $event_item[1];
            $message .= sprintf('[%.3f] ', $event_time_after_request);
            $message .= get_class($event);
            if ($event instanceof QueryExecuted) {
                $message .= sprintf(' (%.1fms) ', $event->time);
                if (is_array($event->result)) {
                    $message .= 'got ' . count($event->result) . ' rows from connection ' . $event->connectionName;
                }
                $message .= "\n";
                $message .= trim($event->sql);
            } elseif ($event instanceof GrpcRequest) {
                $message .= sprintf(
                    ' (%s: start at %.1fms, sent at %.1fms, %s at %.1fms)',
                    $event->method,
                    ($event->request_start_time - $request_time) * 1000,
                    ($event->request_sent_time - $request_time) * 1000,
                    $event->status,
                    ($event->request_complete_time - $request_time) * 1000
                );
            } elseif ($event instanceof GrpcConnect) {
                $message .= sprintf(
                    ' (%s start at %.1fms, complete at %.1fms)',
                    $event->hostname,
                    ($event->connect_start_time - $request_time) * 1000,
                    ($event->connect_complete_time - $request_time) * 1000
                );
            } elseif ($event instanceof HttpRequestCompleted) {
                $message .= sprintf(
                    ' (%.1fms) response size is %d, url is %s',
                    $event->time,
                    $event->getResponseLength(),
                    $event->getRequestUrl()
                );
            } elseif ($event instanceof RedisQueryExecuted) {
                $message .= sprintf(
                    ' (%.1fms) (%s) command is %s, key is %s',
                    $event->time,
                    $event->pool,
                    $event->command,
                    is_array($event->key) ? 'array(' . count($event->key) . ')' : $event->key
                );
            }
            $message .= "\n";
        }
        return $message;
    }
}
