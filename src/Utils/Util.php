<?php

declare(strict_types=1);
/**
 * This file is part of hyperf server projects.
 */
namespace Hyperf\Extend\Utils;

use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Utils\ApplicationContext;
use Hyperf\Utils\Context;
use Hyperf\Extend\Compatibility\Compatibility;
use Hyperf\Extend\Compatibility\HttpRequest;
use Hyperf\Extend\Constant;
use Psr\Http\Message\ServerRequestInterface;
use Swoole\Coroutine;
use Swoole\Http\Request;

/**
 * 一些工具函数.
 */
class Util
{
    public static function getHostName()
    {
        return ini_get('hostname') ?: gethostname();
    }

    /**
     * 格式化数量.
     * @param int $count
     * @return string
     */
    public static function formatIntegerAsCount($count)
    {
        if ($count >= 10000000) {
            return sprintf('%.1f', $count / 1000000) . 'M';
        }
        if ($count >= 1000000) {
            return sprintf('%.2f', $count / 1000000) . 'M';
        }
        if ($count >= 10000) {
            return sprintf('%.1f', $count / 1000) . 'K';
        }
        if ($count >= 1000) {
            return sprintf('%.2f', $count / 1000) . 'K';
        }
        return $count;
    }

    /**
     * @param $key
     * @param $callback
     * @return null|mixed
     */
    public static function cacheableLoad($key, $callback)
    {
        if (Context::has($key)) {
            return Context::get($key);
        }
        $result = $callback();
        Context::set($key, $result);
        return $result;
    }

    /**
     * 获取当前客户端所在时区下的日期
     * @param $timestamp
     */
    public static function dateOfCurrentTimezone($timestamp)
    {
        $tz = intval(HttpRequest::current()->query('_tz', 8));
        $dt = new \DateTime('now', new \DateTimeZone($tz >= 0 ? '+' . $tz : '' . $tz));
        $dt->setTimestamp($timestamp);
        return $dt->format('Y-m-d');
    }

    /**
     * 获取当前客户端所在时区下的今天的日期
     */
    public static function today()
    {
        $tz = intval(HttpRequest::current()->query('_tz', 8));
        $dt = new \DateTime('now', new \DateTimeZone($tz >= 0 ? '+' . $tz : '' . $tz));
        return $dt->format('Y-m-d');
    }

    /**
     * 获取当前请求的trace_id.
     */
    public static function getTraceId()
    {
        $trace_id = null;
        if (ApplicationContext::hasContainer()) {
            if (Context::has(ServerRequestInterface::class)) {
                /** @var RequestInterface $request */
                $request = ApplicationContext::getContainer()->get(ServerRequestInterface::class);
                $trace_id = $request->getHeaderLine('x-trace-id');
            }
            if (empty($trace_id)) {
                $trace_id = Context::get('x-trace-id');
            }
            if (empty($trace_id)) {
                $trace_id = md5(uniqid());
                Context::set('x-trace-id', $trace_id);
            }
        } elseif (class_exists(Coroutine::class) && Coroutine::getuid() > 0) {
            $class_name = '\Middleware\Swoole\SwooleRequestContext';
            $request_context = $class_name::current();
            /** @var Request $request */
            $request = $request_context->getSwooleRequest();
            $trace_id = $request->header['x-trace-id'] ?? null;
            if (empty($trace_id)) {
                $trace_id = $request_context->getCache('x-trace-id');
            }
            if (empty($trace_id)) {
                $trace_id = md5(uniqid());
                $request_context->setCache('x-trace-id', $trace_id);
            }
        } else {
            $trace_id = $_SERVER['HTTP_X_TRACE_ID'] ?? null;
            if (empty($trace_id)) {
                $trace_id = md5(uniqid());
                $_SERVER['HTTP_X_TRACE_ID'] = $trace_id;
            }
        }
        return $trace_id;
    }

    /**
     * 获取部署版本.
     * @return null|string|string[]
     */
    public static function releaseVersion()
    {
        static $release = null;
        if (is_null($release)) {
            $file_path = BASE_PATH . '/version.log';
            try {
                $content = file_get_contents($file_path);
            } catch (\Error $error) {
                $content = '';
            } catch (\Exception $exception) {
                $content = '';
            }
            if (empty($content)) {
                return $content;
            }
            $release = str_replace(['release-v', "\n"], '', $release);
        }
        return $release;
    }

    public static function getMemoryUsage()
    {
        $memory = memory_get_usage();
        return round($memory / 1024 / 1024, 2) . 'MB';
    }
}
