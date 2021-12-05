<?php

declare(strict_types=1);
/**
 * This file is part of hyperf server projects.
 */
namespace Hyperf\Extend\Server;

use Hyperf\Contract\ConfigInterface;
use Hyperf\Dispatcher\HttpDispatcher;
use Hyperf\ExceptionHandler\ExceptionHandlerDispatcher;
use Hyperf\HttpMessage\Server\Request as Psr7Request;
use Hyperf\HttpServer\Contract\CoreMiddlewareInterface;
use Hyperf\HttpServer\MiddlewareManager;
use Hyperf\HttpServer\ResponseEmitter;
use Hyperf\HttpServer\Router\Dispatched;
use Hyperf\HttpServer\Server;
use Hyperf\Utils\Context;
use Hyperf\Utils\Coordinator\Constants;
use Hyperf\Utils\Coordinator\CoordinatorManager;
use Hyperf\Extend\Exception\AllThrowableHandler;
use Hyperf\Extend\Exception\HyperfHttpExceptionHandler;
use Hyperf\Extend\Server\Events\ResponseSendFailed;
use Hyperf\Extend\Server\Events\SlowRequest;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Throwable;

class HttpServer extends Server
{
    public const CONTEXT_KEY_REQUEST_TIME_FLOAT = 'REQUEST_TIME_FLOAT';

    private const TRACE_ID_KEY = 'x-trace-id';

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * 一个请求被标记为慢请求的时间，单位秒.
     * @var float
     */
    private $slow_request_time;

    public function __construct(ContainerInterface $container, HttpDispatcher $dispatcher, ExceptionHandlerDispatcher $exceptionHandlerDispatcher, ResponseEmitter $responseEmitter)
    {
        parent::__construct($container, $dispatcher, $exceptionHandlerDispatcher, $responseEmitter);
        $this->eventDispatcher = $container->get(EventDispatcherInterface::class);
        $this->slow_request_time = $container->get(ConfigInterface::class)->get('server.settings.slow_request_time', 1);
    }

    public function onRequest($request, $response): void
    {
        $request_time = microtime(true);
        Context::set(self::CONTEXT_KEY_REQUEST_TIME_FLOAT, $request_time);
        try {
            CoordinatorManager::until(Constants::WORKER_START)->yield();

            [$psr7Request, $psr7Response] = $this->initRequestAndResponse($request, $response);
            Context::set(self::TRACE_ID_KEY, $this->getTraceId($psr7Request));

            $psr7Request = $this->coreMiddleware->dispatch($psr7Request);
            /** @var Dispatched $dispatched */
            $dispatched = $psr7Request->getAttribute(Dispatched::class);
            $middlewares = $this->middlewares;
            if ($dispatched->isFound()) {
                $registedMiddlewares = MiddlewareManager::get($this->serverName, $dispatched->handler->route, $psr7Request->getMethod());
                $middlewares = array_merge($middlewares, $registedMiddlewares);
            }

            $psr7Response = $this->dispatcher->dispatch($psr7Request, $middlewares, $this->coreMiddleware);
        } catch (Throwable $throwable) {
            // Delegate the exception to exception handler.
            $psr7Response = $this->exceptionHandlerDispatcher->dispatch($throwable, $this->exceptionHandlers);
        } finally {
            // Send the Response to client.
            if (!isset($psr7Response)) {
                return;
            }
            if (!isset($psr7Request) || $psr7Request->getMethod() === 'HEAD') {
                $send_result = $this->responseEmitter->emit($psr7Response, $response, false);
            } else {
                $send_result = $this->responseEmitter->emit($psr7Response, $response, true);
            }
            if ($send_result === false) {
                $this->eventDispatcher->dispatch(new ResponseSendFailed($psr7Request));
            } elseif (microtime(true) - $request_time >= $this->slow_request_time) {
                $this->eventDispatcher->dispatch(new SlowRequest($psr7Request));
            }
        }
    }

    protected function getDefaultExceptionHandler(): array
    {
        return [
            HyperfHttpExceptionHandler::class,
            AllThrowableHandler::class,
        ];
    }

    protected function createCoreMiddleware(): CoreMiddlewareInterface
    {
        return make(HttpServerCoreMiddleware::class, [$this->container, $this->serverName]);
    }

    /**
     * 生成请求的追踪 ID.
     */
    private function getTraceId(Psr7Request $request): string
    {
        if ($request->hasHeader(self::TRACE_ID_KEY)) {
            return $request->getHeader(self::TRACE_ID_KEY)[0];
        }
        if (Context::has(self::TRACE_ID_KEY)) {
            return strval(Context::get(self::TRACE_ID_KEY));
        }
        return md5(uniqid());
    }
}
