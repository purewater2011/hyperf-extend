<?php

declare(strict_types=1);
/**
 * This file is part of hyperf server projects.
 */
namespace Hyperf\Extend\Compatibility;

use Hyperf\ConfigAliyunAcm\ClientInterface;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Server\ServerManager;
use Hyperf\Utils\ApplicationContext;
use Hyperf\Extend\Utils\ENV;
use Psr\Container\ContainerInterface;
use RuntimeException;

class Compatibility
{
    private static $has_swoole_installed = false;

    public static function init()
    {
        // 此处为了避免过于频繁的 class_exists 调用导致性能不佳
        self::$has_swoole_installed = class_exists('\\Swoole\\Coroutine');
    }

    public static function isFpm(): bool
    {
        return in_array(php_sapi_name(), ['fpm-fcgi', 'apache2handler']);
    }

    public static function isInCoroutine(): bool
    {
        return self::$has_swoole_installed && \Swoole\Coroutine::getuid() > 0;
    }

    public static function isSwoole(): bool
    {
        return self::isInCoroutine();
    }

    public static function isNormalSwoole(): bool
    {
        return self::isSwoole() && !self::isHyperf();
    }

    public static function isHyperf(): bool
    {
        return ApplicationContext::hasContainer();
    }

    public static function getConfig($key, $default = null, $throw_if_not_exists = false)
    {
        if (ENV::isDev()) {
            // 开发环境下从 ini 中获取配置信息
        } elseif (ApplicationContext::hasContainer() && gethostname() !== 'ce1-editor6') {
            $container = ApplicationContext::getContainer();
            /** @var ConfigInterface $config */
            $config = $container->get(ConfigInterface::class);

            if (empty(ServerManager::list())) {
                // 程序处于命令行执行模式，并没有启动 server，从 aliyun acm 中获取一次配置信息
                static $is_config_loaded = false;
                if (!$is_config_loaded && $config->get('config_center.enable', false)) {
                    if ($config_in_acm = $container->get(ClientInterface::class)->pull()) {
                        foreach ($config_in_acm as $k => $v) {
                            if (is_string($k)) {
                                $config->set($k, $v);
                            }
                        }
                    }
                    $is_config_loaded = true;
                }
            }

            // 从 hyperf 框架配置中获取
            if ($config->has($key)) {
                return $config->get($key, $default);
            }
            // 从 php.ini 配置中获取
            $config_value_ini = get_cfg_var($key);
            if ($config_value_ini !== false) {
                return $config_value_ini;
            }

            if ($throw_if_not_exists) {
                throw new RuntimeException('cannot find config with key ' . $key);
            }
            return $default;
        }
        if (self::isNormalSwoole()) {
            // 老项目，普通 swoole 正式环境的部署下，从 php.ini 读取配置信息（只加载一次）
            static $config_in_php_ini = null;
            if ($config_in_php_ini === null) {
                $config_in_php_ini = parse_ini_file(php_ini_loaded_file());
            }
            if (!isset($config_in_php_ini[$key]) && $throw_if_not_exists) {
                throw new RuntimeException('Please set ' . $key . ' in ' . php_ini_loaded_file());
            }
            return $config_in_php_ini[$key] ?? $default;
        }
        // 其他场景下
        $config_value = get_cfg_var($key);
        if ($config_value === false && $throw_if_not_exists) {
            throw new RuntimeException('Please set ' . $key . ' in ' . php_ini_loaded_file());
        }
        return $config_value === false ? $default : $config_value;
    }

    /**
     * 获取单例的 container
     * 该方法是为了让一些 hyperf 扩展能在普通的 swoole 环境下也能正常启动
     * 场景为如果这些扩展中有方法依赖了 ContainerInterface 但实际上并未使用 container.
     */
    public static function container(): ContainerInterface
    {
        if (ApplicationContext::hasContainer()) {
            return ApplicationContext::getContainer();
        }
        static $container = null;
        if ($container == null) {
            $container = new class() implements ContainerInterface {
                public function get($id)
                {
                    return null;
                }

                public function has($id): bool 
                {
                    return false;
                }
            };
        }
        return $container;
    }
}

Compatibility::init();
