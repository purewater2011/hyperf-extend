<?php

declare(strict_types=1);
/**
 * This file is part of hyperf server projects.
 */
namespace Hyperf\Extend\Compatibility;

use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Utils\ApplicationContext;
use Hyperf\Utils\Context;
use Psr\Http\Message\ServerRequestInterface;

class HttpRequest
{
    public static function current(): ?RequestInterface
    {
        if (Compatibility::isHyperf()) {
            if (!Context::has(ServerRequestInterface::class)) {
                // 处于命令行环境，会进入这里
                return null;
            }
            return ApplicationContext::getContainer()->get(RequestInterface::class);
        }
        if (Compatibility::isNormalSwoole()) {
            $class_name = '\Middleware\Swoole\SwooleRequestContext';
            $request_context = $class_name::current();
            $cache_key = __CLASS__ . '::' . __METHOD__;
            $cache = $request_context->getCache($cache_key);
            if (!$request_context->isCacheExists($cache_key)) {
                $swoole_request = $request_context->getSwooleRequest();
                $cache = new HttpRequestSwooleWrapper($swoole_request);
                $request_context->setCache($cache_key, $cache);
            }
            return $cache;
        }
        static $fpm_request_wrapper = null;
        if ($fpm_request_wrapper === null) {
            $fpm_request_wrapper = new HttpRequestFpmWrapper();
        }
        return $fpm_request_wrapper;
    }

    /**
     * @param string $key
     * @return string
     */
    public static function getHeader($key)
    {
        $key = strtolower($key);
        return self::current()->header($key) ?: '';
    }

    /**
     * @return string
     */
    public static function getUserAgent()
    {
        return self::current()->header('user-agent') ?: '';
    }

    /**
     * @return string
     */
    public static function getNoSignCheck()
    {
        return self::current()->header('no-sign-check') ?: '';
    }

    /**
     * @return string
     */
    public static function getAccept()
    {
        return self::current()->header('accept') ?: '';
    }
}
