<?php

declare(strict_types=1);
/**
 * This file is part of hyperf server projects.
 */
namespace Hyperf\Extend\Exception;

use Hyperf\HttpMessage\Stream\SwooleStream;
use Hyperf\Extend\Utils\ENV;
use Psr\Http\Message\ResponseInterface;
use Throwable;

class AllThrowableHandler extends HyperfHttpExceptionHandler
{
    public function handle(Throwable $throwable, ResponseInterface $response)
    {
        $this->writeThrowableLog($throwable);
        return $response->withStatus(500)
            ->withAddedHeader('content-type', 'application/json')
            ->withBody(new SwooleStream(json_encode([
                'code' => 500,
                'message' => ENV::isDev() ? $throwable->getTraceAsString() : 'Internal Server Error',
            ])));
    }

    public function isValid(Throwable $throwable): bool
    {
        return true;
    }
}
