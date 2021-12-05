<?php

declare(strict_types=1);
/**
 * This file is part of hyperf server projects.
 */
namespace Hyperf\Extend\BigData;

use Hyperf\Contract\ConfigInterface;
use Hyperf\DbConnection\Db;
use Hyperf\Extend\Utils\ConfigUtil;
use Hyperf\Utils\ApplicationContext;
use Hyperf\Utils\Str;

class CKBaseModel
{
    const TIMEOUT = 3600;

    /**
     * The connection name for the model.
     *
     * @var string
     */
    protected static $connection = 'default';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected static $table;

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected static $primaryKey;

    protected static $connection_pool;

    public static function db($database = '', $pool = 'default')
    {
        if (!empty(self::$connection_pool[$pool . $database])) {
            $ck_client = self::$connection_pool[$pool . $database];
            /* @var \ClickHouseDB\Client $ck_client */
            if ($ck_client->ping() != true) {
                $ck_client = self::reconnect($database, $pool);
            }
        } else {
            $ck_client = self::reconnect($database, $pool);
        }
        return $ck_client;
    }

    public static function reconnect($database = '', $pool = 'default')
    {
        $database = $database ? $database : static::$connection;
        $config = ConfigUtil::clickhouse($pool);
        try {
            $ck_client = retry(3, function () use ($database, $config) {
                $ck_client = new \ClickHouseDB\Client($config);
                $ck_client->database($database);
                $ck_client->setTimeout(self::TIMEOUT);
                $ck_client->setConnectTimeOut(30);
                if ($ck_client->ping() == true) {
                    return $ck_client;
                }
                throw new \RuntimeException('connection error');
            });
        } catch (\Exception $e) {
            $ck_client = new \ClickHouseDB\Client($config);
            $ck_client->database($database);
            $ck_client->setTimeout(self::TIMEOUT);
            $ck_client->setConnectTimeOut(30);
        }
        self::$connection_pool[$pool . $database] = $ck_client;
        return $ck_client;
    }

    public static function findAllByAttributes($attributes, $order = null, $limit = null)
    {
        if (empty($attributes) || !is_array($attributes)) {
            return null;
        }
        $sql = 'select * from ' . static::$table . ' where 1';
        $params = [];
        foreach ($attributes as $k => $v) {
            // 对属性值传入数组的情况进行支持
            if (is_array($v) && empty($v)) {
                return [];
            }
            if (is_array($v) && !empty($v)) {
                $index = 0;
                $sql_in_items = [];
                foreach ($v as $v_item) {
                    $param = ":{$k}{$index}";
                    $sql_in_items[] = $param;
                    $params[$param] = $v_item;
                    ++$index;
                }
                $sql .= " and `{$k}` in (" . join(',', $sql_in_items) . ')';
            } elseif (is_null($v)) {
                $sql .= " and `{$k}` is null";
            } else {
                $sql .= " and `{$k}`=:{$k}";
                $params[":{$k}"] = $v;
            }
        }
        if (!empty($order)) {
            $sql .= " order by {$order}";
        }
        if (!empty($limit)) {
            $sql .= " limit {$limit}";
        }

        return static::findAllBySql($sql, $params);
    }

    public static function findAllBySql($sql, $params = [])
    {
        return static::db()->select($sql, $params)->rows();
    }


    protected function infoClickHouseCreateTableSql(string $table, string $pool, bool $append_language = false)
    {
        $pool_cn = $pool . '_cn';
        $config = ApplicationContext::getContainer()->get(ConfigInterface::class);
        if (!$config->has('databases.' . $pool) && $config->has('databases.' . $pool_cn)) {
            $pool = $pool_cn;
        }
        $target_table_name = $this->getTargetTableName($table);
        if ($append_language) {
            $field_types['language'] = 'UInt8';
        }
        $field_types = $this->getTableFieldTypes($table, $pool);
        $this->info(
            "可使用以下 SQL 在 clickhouse 中建表，需修改其中的 partition, order, sharding:\n\n"
            . $this->generateCreateTableSql($target_table_name, $field_types)
        );
    }

    protected function generateCreateTableSql(string $table, array $field_types)
    {
        $sql = "create table mysql_data.{$table} on cluster default_cluster (\n";
        foreach (array_keys($field_types) as $i => $field) {
            $field_type = $field_types[$field];
            $sql .= "  {$field} {$field_type}";
            if ($i < count($field_types) - 1) {
                $sql .= ',';
            }
            $sql .= "\n";
        }
        $sql .= ') engine = MergeTree() ';
        $sql .= "partition by formatDateTime(created_at, '%Y%m') ";
        $sql .= "order by (user_id) settings index_granularity = 8192;\n\n";
        $sql .= "create table if not exists mysql_data.{$table}_all as mysql_data.{$table}\n";
        $sql .= "engine = Distributed(default_cluster, mysql_data, {$table}, user_id);\n\n\n";
        return $sql;
    }

    private function getTableFieldTypes(string $table, string $pool): array
    {
        $field_types = [];
        $sql = "show columns from {$table}";
        $rows = Db::connection($pool)->select($sql);
        foreach ($rows as $row) {
            preg_match('#^[a-z]+#', $row->Type, $matches);
            $is_unsigned = Str::contains($row->Type, 'unsigned');
            switch ($matches[0]) {
                case 'tinyint':
                    $field_types[$row->Field] = $is_unsigned ? 'UInt8' : 'Int8';
                    break;
                case 'smallint':
                    $field_types[$row->Field] = $is_unsigned ? 'UInt16' : 'Int16';
                    break;
                case 'mediumint':
                    $field_types[$row->Field] = $is_unsigned ? 'UInt24' : 'Int24';
                    break;
                case 'int':
                    $field_types[$row->Field] = $is_unsigned ? 'UInt32' : 'Int32';
                    break;
                case 'bigint':
                    $field_types[$row->Field] = $is_unsigned ? 'UInt64' : 'Int64';
                    break;
                case 'varchar':
                case 'text':
                case 'json':
                    $field_types[$row->Field] = 'String';
                    break;
                case 'timestamp':
                case 'datetime':
                    $field_types[$row->Field] = 'DateTime';
                    break;
            }
            if ($row->Null === 'YES') {
                $field_types[$row->Field] = 'Nullable(' . $field_types[$row->Field] . ')';
            }
        }
        return $field_types;
    }
}
