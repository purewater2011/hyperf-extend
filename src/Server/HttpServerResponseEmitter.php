<?php

declare(strict_types=1);
/**
 * This file is part of hyperf server projects.
 */
namespace Hyperf\Extend\Server;

use Hyperf\HttpMessage\Stream\FileInterface;
use Hyperf\HttpServer\ResponseEmitter;
use Psr\Http\Message\ResponseInterface;

class HttpServerResponseEmitter extends ResponseEmitter
{
    /**
     * 重载该函数的目的是为了能够获知 http 响应是否有成功，以便在响应失败时记录日志.
     * @param mixed $swooleResponse
     */
    public function emit(ResponseInterface $response, $swooleResponse, bool $withContent = true)
    {
        if (strtolower($swooleResponse->header['Upgrade'] ?? '') === 'websocket') {
            return true;
        }
        $this->buildSwooleResponse($swooleResponse, $response);
        $content = $response->getBody();
        if ($content instanceof FileInterface) {
            return $swooleResponse->sendfile($content->getFilename());
        }
        if ($withContent) {
            return $swooleResponse->end($content->getContents());
        }
        return $swooleResponse->end();
    }
}
