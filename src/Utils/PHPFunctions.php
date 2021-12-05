<?php

declare(strict_types=1);
/**
 * This file is part of hyperf server projects.
 */
namespace Hyperf\Extend\Utils;

use Hyperf\Utils\ApplicationContext;
use Hyperf\Extend\Compatibility\Compatibility;

/**
 * @method static sleep($seconds)
 * @method static usleep($micro_seconds)
 * @method static time()
 * @method static string uniqid(string $prefix = "", bool $more_entropy = false)
 * @method static date($format, $timestamp = 'time()')
 */
class PHPFunctions
{
    public static function __callStatic(string $method, $params)
    {
        if (Compatibility::isHyperf()) {
            $f = ApplicationContext::getContainer()->get(self::class);
            return $f->{$method}(...$params);
        }
        return call_user_func($method, ...$params);
    }

    public function __call(string $method, $params)
    {
        if ($method === 'date') {
            return $this->_date(...$params);
        }
        return call_user_func($method, ...$params);
    }

    private function _date($format, $timestamp = 'time()')
    {
        if (empty($timestamp) || $timestamp === 'time()') {
            $timestamp = $this->time();
        }
        return date($format, $timestamp);
    }
}
