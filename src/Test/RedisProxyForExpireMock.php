<?php

declare(strict_types=1);
/**
 * This file is part of hyperf server projects.
 */
namespace Hyperf\Extend\Test;

use Hyperf\Redis\RedisProxy;
use Hyperf\Extend\Utils\PHPFunctions;

/**
 * 测试场景下用于模拟 redis expire.
 */
class RedisProxyForExpireMock extends RedisProxy
{
    private $keys_expire_timestamp = [];

    public function __call($name, $arguments)
    {
        $name_lower = strtolower($name);
        if (in_array($name_lower, ['get', 'exists'])) {
            if ($this->hasExpired($arguments[0])) {
                return false;
            }
        } elseif (in_array($name_lower, ['mget'])) {
            $keys = [];
            $values = [];
            foreach ($arguments[0] as $v) {
                if ($this->hasExpired($v)) {
                    $values[$v] = false;
                } else {
                    $keys[] = $v;
                }
            }
            return $keys ? array_merge(parent::__call($name_lower, [$keys]), $values) : $values;
        } elseif (in_array($name_lower, ['ttl', 'pttl'])) {
            $redis_key = $arguments[0];
            $time = PHPFunctions::time() * 1000;
            if (isset($this->keys_expire_timestamp[$redis_key])) {
                if ($this->keys_expire_timestamp[$redis_key] < $time) {
                    // 已经过期了，在 redis 层按 key 不存在处理，返回 -2
                    return -2;
                }
                $milliseconds = $this->keys_expire_timestamp[$redis_key] - $time;
                if ($name_lower === 'ttl') {
                    return round($milliseconds / 1000);
                }
                return $milliseconds;
            }
        } elseif (in_array($name_lower, ['expire', 'pexpire'])) {
            $redis_key = $arguments[0];
            $ttl = intval($arguments[1]);
            if ($name_lower === 'expire') {
                $ttl *= 1000;
            }
            $this->keys_expire_timestamp[$redis_key] = PHPFunctions::time() * 1000 + $ttl;
        } elseif (in_array($name_lower, ['expireat'])) {
            $redis_key = $arguments[0];
            $this->keys_expire_timestamp[$redis_key] = intval($arguments[1]) * 1000;
            // 传入的参数可能是模拟出来的过去的时间，这个时候不能传到 redis 当中去，所以直接返回 true 进行拦截
            return true;
        } elseif (in_array($name_lower, ['flushall'])) {
            $this->keys_expire_timestamp = [];
        } elseif (in_array($name_lower, ['setex'])) {
            $redis_key = $arguments[0];
            $this->keys_expire_timestamp[$redis_key] = (PHPFunctions::time() + $arguments[1]) * 1000;
        } elseif (in_array($name_lower, ['rawcommand'])) {
            if (in_array(strtolower($arguments[0]), ['exhset', 'exhmget'])) {
                return $this->handleTairHash($arguments);
            }
            if (substr(strtolower($arguments[0]), 0, 3) === 'bf.') {
                return $this->handleTairBloom($arguments);
            }
        }
        return parent::__call($name_lower, $arguments);
    }

    private function hasExpired($redis_key)
    {
        return isset($this->keys_expire_timestamp[$redis_key])
            && $this->keys_expire_timestamp[$redis_key] < PHPFunctions::time() * 1000;
    }

    /**
     * 模拟阿里云TairHash操作.
     * @return array|bool
     */
    private function handleTairHash(array $arguments)
    {
        $prefix = 'tair_hash:';
        switch (strtolower($arguments[0])) {
            case 'exhset':
                $key = $prefix . $arguments[1] . ':' . $arguments[2];
                $expire = 0;
                switch (strtolower($arguments[4])) {
                    case 'ex':
                        $expire = $arguments[5];
                        break;
                    case 'exat':
                        $expire = $arguments[5] - PHPFunctions::time();
                        break;
                    case 'px':
                        $expire = intdiv($arguments[5] / 1000);
                        break;
                    case 'pxat':
                        $expire = intdiv($arguments[5] / 1000) - PHPFunctions::time();
                        break;
                    default:
                        break;
                }
                return $this->setex($key, $arguments[3], $expire);
            case 'exhmget':
                $fields = array_slice($arguments, 2);
                $keys = [];
                foreach ($fields as $field) {
                    $keys[] = $prefix . $arguments[1] . ':' . $field;
                }
                return $this->mget($keys);
            default:
                // todo 其他命令支持
                break;
        }
    }

    /**
     * 模拟阿里云TairBloom操作.
     * @return array|bool
     */
    private function handleTairBloom(array $arguments)
    {
        $prefix = 'tair_bloom:';
        switch (strtolower($arguments[0])) {
            case 'bf.madd':
                $fields = array_slice($arguments, 2);
                $data = [];
                foreach ($fields as $k => $field) {
                    $data[$prefix . $arguments[1] . ':' . $field] = 1;
                    $data[$k] = intval($this->setnx($prefix . $arguments[1] . ':' . $field, 1));
                }
                return $data;
            case 'bf.mexists':
                $fields = array_slice($arguments, 2);
                $keys = [];
                foreach ($fields as $field) {
                    $keys[] = $prefix . $arguments[1] . ':' . $field;
                }
                return $this->mget($keys);
            default:
                // todo 其他命令支持
                break;
        }
    }
}
