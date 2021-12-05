<?php

declare(strict_types=1);
/**
 * This file is part of hyperf server projects.
 */
namespace Hyperf\Extend\Utils;

use Hyperf\Utils\Str;
use Hyperf\Extend\Model\CsvTableModel;

class ClickHouse
{
    public static function showTables(string $pool, string $schema): array
    {
        $sql = 'show tables from ' . $schema;
        $tables = [];
        foreach (explode("\n", self::query($pool, $sql)) as $line) {
            if (empty($line)) {
                continue;
            }
            $tables[] = trim($line);
        }
        return $tables;
    }

    public static function showCreateTable(string $pool, string $schema, string $table): string
    {
        $create_table_sql = self::query($pool, "show create table {$schema}.{$table}");
        $create_table_sql = str_replace('\n', "\n", $create_table_sql);
        return str_replace("\nENGINE", ' ENGINE', $create_table_sql);
    }

    public static function dropPartition(string $pool, string $schema, string $table, string $partition)
    {
        // 判断是否是腾讯的机器
        if (in_array($pool, ['default', 'tencent1'])) {
            $sql = "alter table {$schema}.{$table} on cluster default_cluster drop partition '{$partition}'";
        } else {
            $sql = "alter table {$schema}.{$table} drop partition '{$partition}'";
        }
        return self::query($pool, $sql);
    }

    /**
     * 删除 ClickHouse 数据库表中，比较老的分区，新老的定义使用分区的修改时间进行判断.
     */
    public static function dropPartitionOlder(string $pool, string $schema, string $table, int $days)
    {
        $min_modification_time = date('Y-m-d H:i:s', time() - 86400 * $days);
        $sql = "select partition from system.parts 
                where table='{$table}' and database='{$schema}' 
                    and modification_time<'{$min_modification_time}'";
        $csv = self::queryForCsvTableModel($pool, $sql);
        if (!empty($csv->rows)) {
            foreach ($csv->rows as $row) {
                self::dropPartition($pool, $schema, $table, $row[0]);
            }
        }
    }

    public static function query(string $pool, string $sql, $format = 'TSV'): string
    {
        if (ENVUtil::isDev()) {
            LogUtil::stdout()->info("about to run clickhouse sql:\n" . $sql . "\n");
        }
        $url = self::getClickHouseBaseUrl($pool) . '?default_format=' . $format;
        return HttpUtil::post($url, $sql, [], true, 7200)->getBody()->getContents();
    }

    /**
     * 执行一个 ClickHouse 查询并返回结果为 CsvTableModel 实例
     * 注意：本函数目前没有对转义做支持
     */
    public static function queryForCsvTableModel(string $pool, string $sql): CsvTableModel
    {
        $csv = new CsvTableModel();
        foreach (explode("\n", self::query($pool, $sql, 'TSVWithNamesAndTypes')) as $i => $line) {
            if ($line === null || $line === '') {
                continue;
            }
            $segs = explode("\t", $line);
            if ($i === 0) {
                // 列名
                $csv->headers = $segs;
            } elseif ($i === 1) {
                // 列字段类型
                foreach ($segs as $j => $seg) {
                    if (preg_match('#Nullable\(([^\)]+)\)#', $seg, $matches)) {
                        $segs[$j] = $matches[1];
                    }
                }
                $csv->appendColumnTypes($segs);
            } else {
                // 填入查询结果中的数据行，对数据做格式转换
                $row = [];
                $column_types = $csv->getColumnTypes();
                foreach ($segs as $j => $seg) {
                    switch ($column_types[$j]) {
                        case 'Int8':
                        case 'Int16':
                        case 'Int32':
                        case 'Int64':
                        case 'UInt8':
                        case 'UInt16':
                        case 'UInt32':
                        case 'UInt64':
                            $row[] = intval($seg);
                            break;
                        default:
                            $row[] = $seg === '\N' ? null : $seg;
                    }
                }
                $csv->rows[] = $row;
            }
        }
        return $csv;
    }

    /**
     * 获取一个表的所有列类型信息.
     */
    public static function showTableFieldTypes(string $pool, string $schema, string $table): array
    {
        return self::getTableFieldTypesFromCreateSql(self::showCreateTable($pool, $schema, $table));
    }

    public static function importUrlToClickHouseDefault($url, $schema, $table, $format = 'TSV', $field_appends = [])
    {
        self::importUrlToClickHouse($url, 'default', $schema, $table, $format, $field_appends);
    }

    /**
     * 导入一个 URL 文件至 ClickHouse.
     * @param array $field_appends 在基础上追加一些列，这一列用固定的值填充，其中列名为 key, 值为 value
     * @param mixed $url
     * @param mixed $pool
     * @param mixed $schema
     * @param mixed $table
     * @param mixed $format
     */
    public static function importUrlToClickHouse($url, $pool, $schema, $table, $format = 'TSV', $field_appends = [])
    {
        $table_field_types = self::showTableFieldTypes($pool, $schema, $table);

        // 默认要导入的数据与 ClickHouse 现有的表结构一致
        $field_names_in_file = array_keys($table_field_types);
        if (in_array($format, ['TabSeparatedWithNames', 'TabSeparatedWithNamesAndTypes', 'CSVWithNames'])) {
            // 从文件第一行读取出日志当中有哪些列
            $field_names_in_file = self::readFieldNamesFromUrl($url, $format);
        }

        try {
            // 准备临时表
            $temp_table_name = $table . '_' . md5(uniqid());
            $create_table_sql_temp = "create table {$schema}.{$temp_table_name} (";
            foreach ($field_names_in_file as $i => $field_name) {
                if (isset($field_appends[$field_name])) {
                    // 这些字段是追加进来的，在文件中并不存在
                    continue;
                }
                $field_type = $table_field_types[$field_name] ?? 'Nullable(String)';
                $create_table_sql_temp .= $i === 0 ? '' : ',';
                $create_table_sql_temp .= $field_name . ' ' . $field_type;
            }
            $create_table_sql_temp .= ") engine URL('{$url}', {$format})";
            self::query($pool, $create_table_sql_temp);

            // 拼接导入的 sql
            $select_in_insert = 'select ';
            $insert_sql = "insert into {$schema}.{$table} (";
            foreach ($table_field_types as $field_name => $field_type) {
                if (!in_array($field_name, $field_names_in_file) && !isset($field_appends[$field_name])) {
                    continue;
                }
                $insert_sql .= "`{$field_name}`,";
                if (isset($field_appends[$field_name])) {
                    $field_append_value = $field_appends[$field_name];
                    if (is_string($field_append_value)) {
                        $select_in_insert .= "'{$field_append_value}',";
                    } elseif (is_int($field_append_value) || is_float($field_append_value)) {
                        $select_in_insert .= "{$field_append_value},";
                    } elseif (is_bool($field_append_value)) {
                        $select_in_insert .= ($field_append_value ? 1 : 0) . ',';
                    } else {
                        throw new \RuntimeException('unexpected append field value type ' . gettype($field_append_value));
                    }
                } else {
                    $select_in_insert .= "`{$field_name}`,";
                }
            }
            $select_in_insert = substr($select_in_insert, 0, strlen($select_in_insert) - 1);
            $insert_sql = substr($insert_sql, 0, strlen($insert_sql) - 1) . ') ';
            $insert_sql .= $select_in_insert . " from {$schema}.{$temp_table_name}";
            $query = [
                'input_format_allow_errors_num' => 100,
                'input_format_allow_errors_ratio' => 0.05,
            ];
            $ck_url = self::getClickHouseBaseUrl($pool) . '?' . http_build_query($query);
            HttpUtil::post($ck_url, $insert_sql, [], true, 3600)->getBody()->getContents();
        } finally {
            // 把临时表删掉
            self::query($pool, "drop table if exists {$schema}.{$temp_table_name}");
        }
    }

    public static function getClickHouseBaseUrl(string $pool)
    {
        $config = ConfigUtil::clickhouse($pool);
        return "http://{$config['username']}:" . $config['password'] . "@{$config['host']}:{$config['port']}";
    }

    private static function readFieldNamesFromUrl($url, $format): array
    {
        $is_gz = Str::endsWith(parse_url($url)['path'], '.gz');
        $temp_file_path = '/tmp/' . md5(uniqid());
        $response = HttpUtil::get($url, ['Range' => 'bytes=0-102400']);
        file_put_contents($temp_file_path, $response->getBody()->getContents());
        $handle = $is_gz ? gzopen($temp_file_path, 'r') : fopen($temp_file_path, 'r');
        if ($handle === false) {
            throw new \RuntimeException('failed to get field names from url ' . $url);
        }
        $first_line = $is_gz ? gzgets($handle) : fgets($handle);
        $first_line = trim($first_line);
        switch ($format) {
            case 'TabSeparatedWithNames':
            case 'TabSeparatedWithNamesAndTypes':
                return explode("\t", $first_line);
            case 'CSVWithNames':
                return explode(',', $first_line);
        }
        throw new \RuntimeException('format not supported of ' . $format);
    }

    /**
     * 从表创建语句中获取到列类型信息.
     */
    private static function getTableFieldTypesFromCreateSql(string $create_sql): array
    {
        $create_sql = str_replace("\n", ' ', $create_sql);
        if (!preg_match('#(\([\S ]+)ENGINE#', $create_sql, $matches)) {
            throw new \RuntimeException('clickhouse create sql is not expected ' . $create_sql);
        }
        // 去除建表语句字段的最最头尾一对括号
        $temp_sql = trim($matches[1]);
        $temp_sql = substr($temp_sql, 1, strlen($temp_sql) - 2);
        $table_field_types = [];
        if (preg_match_all('/`([^`]+)` ([^,]+)/', $temp_sql, $matches)) {
            foreach ($matches[1] as $i => $field) {
                $table_field_types[$field] = $matches[2][$i];
            }
        }
        if (preg_match_all('/`([^`]+)` (Decimal\([\d+],[^,]+)/i', $temp_sql, $matches)) {
            // 解析带逗号的格式，覆盖前面取出的字段类型
            foreach ($matches[1] as $i => $field) {
                $table_field_types[$field] = $matches[2][$i];
            }
        }
        return $table_field_types;
    }
}
