<?php

declare(strict_types=1);
/**
 * This file is part of hyperf server projects.
 */
namespace Hyperf\Extend\Command\DataExport;

use Hyperf\Command\Annotation\Command;
use Hyperf\Contract\ConfigInterface;
use Hyperf\DbConnection\Db;
use Hyperf\Utils\ApplicationContext;
use Hyperf\Utils\Str;
use Hyperf\Extend\Command\MultiActionBaseCommand;
use Hyperf\Extend\Model\DbQuery;
use Hyperf\Extend\Model\TableBuilder;
use Hyperf\Extend\Utils\DateUtil;

/**
 * 以 tsv 格式导出 mysql 数据
 * @Command
 */
class MysqlCommand extends MultiActionBaseCommand
{
    private const BATCH_SIZE = 5000;

    protected $name = 'data-export:mysql';

    public function exportTableAction(string $table, string $pool = 'default', string $primary_key = 'id')
    {
        $target_table_name = $this->getTargetTableName($table);
        $save_file_path = $this->getTempFilePath($target_table_name);
        $sql = "select * from {$table} where {$primary_key}>=\${min_id} order by {$primary_key} limit \${limit}";
        $this->exportTableBySql($save_file_path, $pool, $sql, $primary_key);
        return $save_file_path;
    }

    /**
     * 导出某一天产生的数据库表数据，按天增量导出支持.
     * @param string $table 要导出的数据库表名
     * @param string $pool 要导出的数据库的连接池的名称
     * @param string $date 要导出的数据的日期
     * @param string $field 表中的时间字段的名字，默认 created_at
     */
    public function exportTableOnDateAction($table, $pool = 'default', $date = 'yesterday', $field = 'created_at')
    {
        $date = DateUtil::getRealDate($date);
        $target_table_name = $this->getTargetTableName($table);
        $save_file_path = $this->getTempFilePath($target_table_name);
        $sql = "select * from {$table} 
                where id>=\${min_id} 
                    and {$field}>='{$date}'
                    and {$field}<='{$date} 23:59:59'
                order by id limit \${limit}";
        $this->exportTableBySql($save_file_path, $pool, $sql);
        return $save_file_path;
    }

    public function exportTableSplit256Action(string $table, string $pool = 'default')
    {
        $target_table_name = $this->getTargetTableName($table);
        $save_file_path = $this->getTempFilePath($target_table_name);
        for ($i = 0; $i < 256; ++$i) {
            $table_split = sprintf('%s_%02x', $table, $i);
            $this->info("about to export table {$table_split}");
            $sql = "select * from {$table_split} where id>=\${min_id} order by id limit \${limit}";
            $this->exportTableBySql($save_file_path, $pool, $sql);
        }
        return $save_file_path;
    }

    /**
     * 导出某一天产生的数据库拆 256 分表数据，按天增量导出.
     * @param string $table 要导出的数据库表名
     * @param string $pool 要导出的数据库的连接池的名称
     * @param string $date 要导出的数据的日期
     * @param string $field 表中的时间字段的名字，默认 created_at
     */
    public function exportTableSplit256OnDateAction($table, $pool = 'default', $date = 'yesterday', $field = 'created_at')
    {
        $date = DateUtil::getRealDate($date);
        $target_table_name = $this->getTargetTableName($table);
        $save_file_path = $this->getTempFilePath($target_table_name);
        for ($i = 0; $i < 256; ++$i) {
            $table_split = sprintf('%s_%02x', $table, $i);
            $this->info("about to export table {$table_split}");
            $sql = "select * from {$table_split} 
                    where id>=\${min_id} 
                        and {$field}>='{$date}'
                        and {$field}<='{$date} 23:59:59'
                    order by id limit \${limit}";
            $this->exportTableBySql($save_file_path, $pool, $sql);
        }
        return $save_file_path;
    }

    protected function exportTableBySql(string $save_file_path, string $pool, $sql, string $primary_key = 'id')
    {
        $builder = new TableBuilder();
        $builder->addDbQuery($sql, $pool);
        $this->exportTable($builder, [], $save_file_path, $primary_key);
        return $save_file_path;
    }

    protected function exportTableSplit256BySql(string $save_file_path, string $pool, $sql, $table)
    {
        return $this->exportTableSplit256($save_file_path, function (int $suffix) use ($pool, $sql, $table) {
            $config = ApplicationContext::getContainer()->get(ConfigInterface::class);
            if (!$config->has('databases.' . $pool)) {
                $this->error('cannot find database config with pool ' . $pool);
                return null;
            }
            $table_split = sprintf('%s_%02x', $table, $suffix);
            $sql = str_replace('${table}', $table_split, $sql);
            $builder = new TableBuilder();
            $builder->addDbQuery($sql, $pool);
            return $builder;
        });
    }

    /**
     * 获取 mysql 数据库中的表名在数据导出完成之后对应到 clickhouse 中的表名.
     */
    protected function getTargetTableName(string $table)
    {
        if (Str::endsWith($table, '_00')) {
            $table = substr($table, 0, strlen($table) - 3);
        }
        return $table;
    }

    /**
     * 获取临时文件路径.
     * @return string
     */
    protected function getTempFilePath(string $target_table_name)
    {
        return BASE_PATH . '/runtime/' . $target_table_name . '_' . substr(md5(uniqid()), 0, 4) . '.tsv.gz';
    }

    protected function getTempFilePathWithoutGzip(string $target_table_name)
    {
        return BASE_PATH . '/runtime/' . $target_table_name . '_' . substr(md5(uniqid()), 0, 4) . '.tsv';
    }

    /**
     * @param string $save_file_path 导出的临时文件路径
     * @param callable $callback 用来生成某个分表对应的 TableBuilder 回调函数，声明示例：function (int $suffix): TableBuilder
     */
    protected function exportTableSplit256(string $save_file_path, callable $callback)
    {
        for ($suffix = 0; $suffix < 256; ++$suffix) {
            $table_builder = $callback($suffix);
            if (empty($table_builder)) {
                continue;
            }
            $this->exportTable($table_builder, [], $save_file_path);
        }
        return $save_file_path;
    }

    protected function exportTable(TableBuilder $builder, array $field_types, string $save_file_path, string $primary_key = 'id')
    {
        $is_gz = Str::endsWith($save_file_path, '.gz');
        $handle = $is_gz ? gzopen($save_file_path, 'a') : fopen($save_file_path, 'a');
        $min_id = 0;
        while (true) {
            foreach ($builder->getQueries() as $query) {
                if ($query instanceof DbQuery) {
                    Db::connection($query->getPool())->unsetEventDispatcher();
                }
            }
            $csv = $builder->build(['min_id' => $min_id + 1, 'limit' => self::BATCH_SIZE]);
            $table_column_types = array_merge($csv->getColumnTypes(), $field_types);
            foreach ($csv->rows as $row) {
                $row_formatted = [];
                for ($i = 0; $i < count($csv->headers); ++$i) {
                    $value = $row[$i] ?? null;
                    if ($value === null) {
                        $value = '\N';
                    } else {
                        $value = str_replace(['\\', "\t", "\n", "\r"], ['\\\\', ' ', ' ', ' '], $value);
                    }
                    $field_name = $csv->headers[$i];
                    $column_type = $table_column_types[$field_name] ?? null;
                    if (in_array($column_type, ['DateTime', 'TIMESTAMP', 'DATETIME'])
                        && is_string($value) && Str::contains($value, '-')) {
                        $row_formatted[] = strtotime($value);
                    } else {
                        $row_formatted[] = $value;
                    }
                }
                $line = join("\t", $row_formatted) . "\n";
                $is_gz ? gzwrite($handle, $line) : fwrite($handle, $line);
                $min_id = max($min_id, $csv->cell($row, $primary_key));
            }
            $this->info('exported ' . count($csv->rows) . " rows to {$primary_key} {$min_id}");
            if (empty($csv->rows)) {
                break;
            }
        }

        $is_gz ? gzclose($handle) : fclose($handle);
    }

}
