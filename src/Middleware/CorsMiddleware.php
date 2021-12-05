<?php

declare(strict_types=1);
/**
 * This file is part of hyperf server projects.
 */
namespace Hyperf\Extend\Middleware;

use Hyperf\Utils\Context;
use Hyperf\Utils\Str;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class CorsMiddleware implements MiddlewareInterface
{
    private const WHITELIST = [
        'http://10.1.',
        'http://192.168.',
        'http://localhost',
    ];

    private const ALLOW_HEADERS = 'DNT,Keep-Alive,User-Agent,Cache-Control,Content-Type,Authorization,X-Token';

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = Context::get(ResponseInterface::class);
        $response = $this->dealWithCORS($request, $response);
        Context::set(ResponseInterface::class, $response);

        if ($request->getMethod() == 'OPTIONS') {
            return $response;
        }

        return $handler->handle($request);
    }

    private function dealWithCORS(ServerRequestInterface $request, ResponseInterface $response)
    {
        $origin = $request->getHeader('origin');
        $origin = $origin ? $origin[0] : '';
        if (!empty($origin) && Str::startsWith($origin, self::WHITELIST)) {
            $response = $response->withHeader('Access-Control-Allow-Origin', $origin)
                ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, OPTIONS')
                ->withHeader('Access-Control-Allow-Credentials', 'true')
                ->withHeader('Access-Control-Allow-Headers', self::ALLOW_HEADERS);
        }
        return $response;
    }
}
