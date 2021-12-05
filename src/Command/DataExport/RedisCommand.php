<?php

declare(strict_types=1);
/**
 * This file is part of hyperf server projects.
 */
namespace Hyperf\Extend\Command\DataExport;

use Hyperf\Command\Annotation\Command;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Redis\RedisFactory;
use Hyperf\Utils\ApplicationContext;
use Hyperf\Utils\Str;
use Hyperf\Extend\Command\MultiActionBaseCommand;
use Redis;

/**
 * 以 tsv 格式导出 redis 数据至 OSS.
 * @Command
 */
class RedisCommand extends MultiActionBaseCommand
{
    protected $name = 'data-export:redis';

    public function __construct(string $name = null)
    {
        parent::__construct($name);
    }

    /**
     * 把 Redis 数据库当成普通的 key => value 型字符串导出.
     * @param mixed $pool
     * @param mixed $value_type
     * @param mixed $database
     * @param mixed $pattern
     */
    public function exportAction($pool = 'default', $value_type = 'String', $database = '0', $pattern = '*')
    {
        $pool_name = $this->getPoolName($pool);
        $save_file_path = $this->getTempFilePath($pool_name);
        $this->exportRedis($save_file_path, $pool, intval($database), $pattern, function ($redis, $batch_keys) use ($pool, $database) {
            /** @var Redis $redis */
            $lines = [];
            foreach ($redis->mget($batch_keys) as $i => $value) {
                if ($value === false) {
                    $this->warn('value is not a string of redis key ' . $batch_keys[$i]);
                    continue;
                }
                $lines = array_merge($lines, $this->formatLineForRedisGet($pool, $database, $batch_keys[$i], $value));
            }
            return $lines;
        });
        return $save_file_path;
    }

    public function handle()
    {
        ini_set('memory_limit', intval($this->getTotalMemory() * 0.9) . 'G');
        parent::handle();
    }

    public function exportListAction($pool = 'default', $value_type = 'Int32', $database = '0', $pattern = '*')
    {
        $pool_name = $this->getPoolName($pool);
        $save_file_path = $this->getTempFilePath($pool_name);
        $this->exportRedis($save_file_path, $pool, intval($database), $pattern, function ($redis, $batch_keys) use ($pool, $database) {
            /** @var Redis $redis */
            $lines = [];
            $redis->multi();
            foreach ($batch_keys as $key) {
                $redis->lRange($key, 0, -1);
            }
            $values = $redis->exec();
            foreach ($batch_keys as $i => $key) {
                if ($values[$i] === false) {
                    $this->warn('value is not a list of redis key ' . $key);
                    continue;
                }
                $lines = array_merge($lines, $this->formatLineForRedisList($pool, $database, $key, $values[$i] ?? []));
            }
            return $lines;
        });
        return $save_file_path;
    }

    public function exportZSetAction($pool = 'default', $score_type = 'Float32', $value_type = 'Int32', $database = '0', $pattern = '*')
    {
        $pool_name = $this->getPoolName($pool);
        $save_file_path = $this->getTempFilePath($pool_name);
        $this->exportRedis($save_file_path, $pool, intval($database), $pattern, function ($redis, $batch_keys) use ($pool, $database, $save_file_path) {
            /** @var Redis $redis */
            $lines = [];
            $redis->multi();
            foreach ($batch_keys as $key) {
                $redis->zRange($key, 0, -1, true);
            }
            $values = $redis->exec();
            foreach ($batch_keys as $i => $key) {
                if ($values[$i] === false) {
                    $this->warn('value is not a zset of redis key ' . $key);
                    continue;
                }
                foreach ($values[$i] as $value => $score) {
                    $lines = array_merge($lines, $this->formatLineForRedisZSet($pool, $database, $key, $value, $score));
                    if (count($lines) > 10000) {
                        $this->writeLinesToFile($save_file_path, $lines);
                        $lines = [];
                    }
                }
            }
            return $lines;
        });
        return $save_file_path;
    }

    /**
     * 导出 redis 数据到文件.
     * @param string $save_file_path 要保存的文件路径，自动识别 gz 文件后缀
     * @param string $pool redis 连接池的名称
     * @param int $database redis 数据库编号
     * @param string $pattern 要导出的 key 规则
     * @param callable $batch_callback 从 redis 取出一批 key 对应的值的回调函数，回调声明为 function(array $batch_keys): array，返回值为要写入到文件中的数据行
     * @param int $batch_size 执行数据导出时的 key 批大小，默认一万一批
     */
    protected function exportRedis(string $save_file_path, string $pool, int $database, string $pattern, callable $batch_callback, $batch_size = 10000)
    {
        $this->foreachRedisBatchedKeys($pool, $database, $pattern, function ($redis, array $batch_keys) use ($save_file_path, $batch_callback) {
            $this->writeLinesToFile($save_file_path, $batch_callback($redis, $batch_keys));
        }, $batch_size);
    }

    protected function writeLinesToFile(string $save_file_path, array $lines)
    {
        if (!empty($lines)) {
            $is_gz = Str::endsWith($save_file_path, '.gz');
            $handle = $is_gz ? gzopen($save_file_path, 'a') : fopen($save_file_path, 'a');
            foreach ($lines as $line) {
                $is_gz ? gzwrite($handle, $line . "\n") : fwrite($handle, $line . "\n");
            }
            $is_gz ? gzclose($handle) : fclose($handle);
        }
    }

    protected function foreachRedisBatchedKeys(string $pool, int $database, string $pattern, callable $batch_callback, $batch_size = 10000)
    {
        /** @var \Redis $redis */
        $redis = ApplicationContext::getContainer()->get(RedisFactory::class)->get($pool);
        $redis->select($database);
        $keys = $redis->keys($pattern);
        $this->info('found ' . count($keys) . ' keys in redis ' . $pool);
        for ($offset = 0; $offset < count($keys); $offset += $batch_size) {
            $batch_callback($redis, array_slice($keys, $offset, $batch_size));
            $this->info('exported ' . ($offset + $batch_size) . ' keys from redis ' . $pool);
        }
    }

    protected function getPoolName($pool)
    {
        $app_name = ApplicationContext::getContainer()->get(ConfigInterface::class)->get('app_name');
        $project = str_replace('-', '_', $app_name);
        return $pool == 'default' ? 'redis_' . $project . '_default' : $pool;
    }

    protected function formatCellForTsvOutput($value): string
    {
        if ($value === null) {
            return '\N';
        }
        if (is_string($value)) {
            return str_replace(['\\', "\t", "\n", "\r"], ['\\\\', ' ', ' ', ' '], $value);
        }
        return strval($value);
    }

    /**
     * 获取临时文件路径.
     * @return string
     */
    protected function getTempFilePath(string $pool_name)
    {
        return BASE_PATH . '/runtime/' . $pool_name . '_' . substr(md5(uniqid()), 0, 4) . '.tsv.gz';
    }

    /**
     * 提供给子类继承，以实现 redis 数据取出之后进行再加工.
     * @param mixed $pool
     * @param mixed $database
     * @return string[]
     */
    protected function formatLineForRedisGet($pool, $database, string $key, string $value): array
    {
        return [$this->formatCellForTsvOutput($key) . "\t" . $this->formatCellForTsvOutput($value)];
    }

    /**
     * 提供给子类继承，以实现 redis 数据取出之后进行再加工.
     * @param mixed $pool
     * @param mixed $database
     * @return string[]
     */
    protected function formatLineForRedisList($pool, $database, string $key, array $value): array
    {
        return [$this->formatCellForTsvOutput($key) . "\t" . $this->formatCellForTsvOutput(json_encode($value))];
    }

    /**
     * 提供给子类继承，以实现 redis 数据取出之后进行再加工.
     * @param mixed $value
     * @param mixed $score
     * @param mixed $pool
     * @param mixed $database
     * @return string[]
     */
    protected function formatLineForRedisZSet($pool, $database, string $key, $value, $score): array
    {
        return [
            $this->formatCellForTsvOutput($key) . "\t"
            . $this->formatCellForTsvOutput($value) . "\t"
            . $score,
        ];
    }

    private function getTotalMemory()
    {
        if (PHP_OS === 'Darwin') {
            return 8;
        }
        $command = "cat /proc/meminfo | grep MemTotal | grep -oE '[0-9]+'";
        exec($command, $output);

        return intval($output[0]) / 1024 / 1024;
    }
}
