<?php

declare(strict_types=1);
/**
 * This file is part of hyperf server projects.
 */
namespace Hyperf\Extend\Server;

use FastRoute\Dispatcher;
use Hyperf\HttpServer\Contract\CoreMiddlewareInterface;
use Hyperf\Extend\Exception\AllThrowableHandler;
use Hyperf\Extend\Exception\HyperfGrpcExceptionHandler;

class GrpcServer extends HttpServer
{
    public function getDispatcher(): Dispatcher
    {
        return $this->coreMiddleware->getDispatcher();
    }

    public function getCoreMiddleware()
    {
        return $this->coreMiddleware;
    }

    protected function getDefaultExceptionHandler(): array
    {
        return [
            HyperfGrpcExceptionHandler::class,
            AllThrowableHandler::class,
        ];
    }

    protected function createCoreMiddleware(): CoreMiddlewareInterface
    {
        return make(GrpcServerCoreMiddleware::class, [$this->container, $this->serverName]);
    }
}
