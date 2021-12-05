<?php

declare(strict_types=1);
/**
 * This file is part of hyperf server projects.
 */
namespace Hyperf\Extend\Middleware;

use Hyperf\Utils\Context;
use Hyperf\Extend\IPIP\IPIP;
use Hyperf\Extend\Utils\ENV;
use Hyperf\Extend\Utils\Util;
use Hyperf\Extend\Utils\ClientInfoUtil;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class GrpcAccessLogMiddleware extends ApiAccessLogMiddleware
{
    protected function getLogFilePath(): string
    {
        $folder_path = BASE_PATH . '/runtime/grpc_access_logs/';
        if (ENV::isPre()) {
            $folder_path = BASE_PATH . '/runtime/pre_logs/grpc_access_logs/';
        }
        return $folder_path . date('Y-m-d', time()) . '/' . date('H') . '.log';
    }

    protected function formatLogLine(ServerRequestInterface $request, ResponseInterface $response, float $request_time): string
    {
        $ip = ClientInfoUtil::getRemoteAddress();
        $params_json = Context::get('GRPC_PARAMS_JSON', '');
        $response_json = Context::get('GRPC_RESPONSE_JSON', '');

        $log_items = [
            date('Y-m-d H:i:s'),
            Util::getTraceId(),
            $response->getStatusCode(),
            $request->getUri()->getPath(),
            $ip,
            IPIP::findCountryOrRegion($ip),
            $params_json,
            $response_json,
            sprintf('%.2f', (microtime(true) - $request_time) * 1000),
        ];
        return join("\t", $log_items) . "\n";
    }
}
