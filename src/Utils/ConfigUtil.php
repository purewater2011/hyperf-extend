<?php

declare(strict_types=1);
/**
 * This file is part of hyperf server projects.
 */
namespace Hyperf\Extend\Utils;

use Hyperf\Contract\ConfigInterface;
use Hyperf\Utils\ApplicationContext;
use Hyperf\Extend\Compatibility\Compatibility;
use RuntimeException;

class ConfigUtil
{
    public static function redis(string $host, int $max_connections = 30, string $auth = null, int $port = 6379, int $db = 0, array $options = [])
    {
        $return = [
            'host' => $host,
            'auth' => $auth,
            'port' => (int) $port,
            'db' => $db > 0 ? $db : 0,
            'pool' => [
                'min_connections' => 1,
                'max_connections' => $max_connections,
                'connect_timeout' => 1.0,
                'wait_timeout' => 0.01,
                'heartbeat' => -1,
                'max_idle_time' => (float) env('REDIS_MAX_IDLE_TIME', 60),
            ],
        ];
        if ($options) {
            $return['options'] = $options;
        }
        return $return;
    }

    public static function mysql(string $database, string $username, string $password, array $masters, array $slaves = [], $max_connections = 20): array
    {
        $get_mysql_server_config_item = function ($config_item): array {
            if (is_array($config_item)) {
                return $config_item;
            }
            if (is_string($config_item)) {
                $segs = explode(':', $config_item);
                return ['host' => DNS::resolve($segs[0]), 'port' => isset($segs[1]) ? intval($segs[1]) : 3306];
            }
            throw new RuntimeException('unexpected mysql server info ' . json_encode($config_item));
        };
        $get_mysql_server_config = function ($configs) use ($get_mysql_server_config_item): array {
            if (isset($configs['host']) || isset($configs['unix_socket'])) {
                return $configs;
            }
            $result = [];
            foreach ($configs as $config_item) {
                $result[] = $get_mysql_server_config_item($config_item);
            }
            return $result;
        };
        $write_configs = $get_mysql_server_config($masters);
        $read_configs = $get_mysql_server_config($slaves);
        if (empty($read_configs)) {
            $read_configs = $write_configs;
        }
        return [
            'driver' => 'mysql',
            'read' => $read_configs,
            'write' => $write_configs,
            'sticky' => true,
            'database' => $database,
            'username' => $username,
            'password' => $password,
            'charset' => 'utf8mb4',
            'collation' => env('DB_COLLATION', 'utf8mb4_general_ci'),
            'pool' => [
                'min_connections' => 1,
                'max_connections' => $max_connections,
                'connect_timeout' => 1,
                'wait_timeout' => 0.01,
                'heartbeat' => -1,
                'max_idle_time' => (float) env('DB_MAX_IDLE_TIME', 60),
            ],
            'cache' => [
                'pool' => env('BASE_CACHE', 'cache'),
                'prefix' => $database,
            ],
            'commands' => [
                'gen:model' => [
                    'with_comments' => true,
                ],
            ],
        ];
    }

    public static function clickhouse($pool = 'default'): array
    {
        $config = make(ConfigInterface::class)->get('clickhouse');
        if (isset($config[$pool])) {
            throw new \RuntimeException("clickhouse not has {$pool} config!");
        }
        return $config[$pool];
    }

    public static function mysqlDevEnv(string $database, bool $unix_socket = true): array
    {
        $host = env('DB_HOST', 'localhost');
        $port = env('DB_PORT', 3306);
        $account = env('DB_USERNAME', 'root');
        $password = env('DB_PASSWORD');
        if ($password === null) {
            $password = 'root';
        }

        if (env('UNIX_SOCKET') !== null) {
            $unix_socket = env('UNIX_SOCKET');
        }

        // 历史遗留MAMP套件的mysql进程文件
        // 如果该文件不存在则使用env配置
        $unix_socket = '/Applications/MAMP/tmp/mysql/mysql.sock';
        $masters = ['unix_socket' => $unix_socket];
        if (!$unix_socket || !file_exists($unix_socket)) {
            $masters = [$host . ':' . $port];
        }

        return static::mysql($database, $account, $password, $masters);
    }

    public static function mysqlTestEnv(string $database): array
    {
        $hosts = ['127.0.0.1:3306'];
        return static::mysql($database, 'username', 'password', $hosts);
    }

    public static function mysqlProductionEnv(string $database, array $masters, array $slaves = [], $max_connections = 20): array
    {
        return static::mysql($database, 'username', 'password', $masters, $slaves, $max_connections);
    }

    public static function mysqlAutoEnv(
        string $database,
        array $production_masters,
        array $production_slaves = [],
        array $additional_options = [],
        int $max_connections = 20,
        string $password = 'password'
    ): array {
        $config = static::mysql($database, 'username', $password, $production_masters, $production_slaves, $max_connections);
        if (ENVUtil::isTest()) {
            $config = static::mysqlTestEnv($database);
        } elseif (ENVUtil::isDev()) {
            $config = static::mysqlDevEnv($database);
        }
        return array_merge($config, $additional_options);
    }

    public static function getSimpleConfig($key, $default = null)
    {
        $container = ApplicationContext::getContainer();
        $config = $container->get(ConfigInterface::class)->get('simple_configs');
        $result = $config[$key] ?? $default;
        return $result;
    }

    public static function redisDevEnv(int $db = 0, array $options = []): array
    {
        return static::redis(env('DEV_REDIS_HOST', 'localhost'), 30, null, 6379, $db, $options);
    }

    public static function redisProductionEnv(string $host, int $max_connections = 30, string $auth = null, int $port = 6379, int $db = 0, array $options = []): array
    {
        if (!empty($auth)) {
            return static::redis($host, $max_connections, $auth, $port, $db, $options);
        }
        return static::redis($host, $max_connections, 'auth', $port, $db, $options);
    }

    public static function redisAutoEnv(string $production_host, int $production_port = 6379, string $auth = null, int $max_connections = 30)
    {
        if (ENVUtil::isDev() || ENVUtil::isTest()) {
            return self::redisDevEnv();
        }
        return self::redisProductionEnv($production_host, $max_connections, $auth, $production_port);
    }

    public static function redisWithDbAutoEnv(string $production_host, int $db, string $auth = null, int $port = 6379)
    {
        if (ENVUtil::isDev() || ENVUtil::isTest()) {
            return self::redisDevEnv($db);
        }
        return self::redisProductionEnv($production_host, 30, $auth, $port, $db);
    }

    public static function getMysqlConfigMd5($params)
    {
        $format_configs = function ($params) {
            $str = '';
            foreach ($params as $param) {
                if (is_array($param)) {
                    foreach ($param as $val) {
                        $str .= $val . '&';
                    }
                } else {
                    $str .= $param . '&';
                }
            }
            return $str;
        };
        $result = '';
        if (isset($params['write'])) {
            $result .= $format_configs($params['write']);
        }
        if (isset($params['read'])) {
            $result .= $format_configs($params['read']);
        }
        return md5($result);
    }

    public static function getCommonConfig(string $key, $default = null)
    {
        if (Compatibility::isHyperf()) {
            return config($key, get_cfg_var($key) ?: $default);
        }
        $config_path = '/dev/shm/common_configs.php';
        if (ENV::isDev()) {
            $config_path = '/tmp/common_configs.php';
        }
        if (file_exists($config_path)) {
            $common_configs = require $config_path;
            return data_get($common_configs, $key, $default);
        }
        return get_cfg_var($key) ?: $default;
    }
}
