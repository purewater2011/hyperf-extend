<?php

declare(strict_types=1);
/**
 * This file is part of hyperf server projects.
 */
namespace Hyperf\Extend\Utils;

use Hyperf\Contract\ConfigInterface;
use Hyperf\Database\ConnectionInterface;
use Hyperf\Database\ConnectionResolverInterface;
use Hyperf\DbConnection\Db;
use Hyperf\Utils\ApplicationContext;
use Hyperf\Extend\Overrides\HyperfConnectionResolver as ConnectionResolver;

class DbUtil
{
    /**
     * 批量更新表里面的数据.
     * @param ConnectionInterface $db 数据库连接
     * @param string $table 表名
     * @param string $update_field 需要更新的字段名
     * @param string $case_field 作为查询的字段名
     * @param array $values 要更新的数据
     */
    public static function batchUpdateFieldOfTable(
        ConnectionInterface $db,
        string $table,
        string $update_field,
        string $case_field,
        array $values
    ) {
        $batch_size = 500;
        $sql_part1 = " update `{$table}` set `{$update_field}` = case `{$case_field}` ";
        $sql_part2 = " end where `{$case_field}` in (";
        $sql_part3 = ')';
        $index = 0;
        $params = [];
        $sql_whens = [];
        $sql_ins = [];
        foreach ($values as $when => $value) {
            $sql_whens[] = " when :w{$index} then :v{$index}";
            $sql_ins[] = ':i' . $index;
            $params[':w' . $index] = $when;
            $params[':i' . $index] = $when;
            $params[':v' . $index] = $value;
            ++$index;
            if (count($sql_whens) == $batch_size) {
                $sql = $sql_part1 . join('', $sql_whens) . $sql_part2 . join(',', $sql_ins) . $sql_part3;
                if (!$db->statement($sql, $params)) {
                    throw new \RuntimeException('failed to execute sql ' . $sql);
                    break;
                }
                $index = 0;
                $params = [];
                $sql_whens = [];
                $sql_ins = [];
            }
        }
        if (!empty($sql_whens)) {
            $sql = $sql_part1 . join('', $sql_whens) . $sql_part2 . join(',', $sql_ins) . $sql_part3;
            if (!$db->statement($sql, $params)) {
                throw new \RuntimeException('failed to execute sql ' . $sql);
            }
        }
    }

    /**
     * 批量更新表中多个字段.
     * @param ConnectionInterface $db 数据库连接
     * @param string $table 表名
     * @param string[] $update_fields 需要更新的字段名
     * @param string $case_field 作为查询的字段名
     * @param array $values 要更新的数据,索引key为查询字段对应的值,$values = [ 1 => [80,'苹果'], 2 => [90,'香蕉']]
     */
    public static function batchUpdateMultiFieldsOfTable(ConnectionInterface $db, string $table, array $update_fields, string $case_field, array $values)
    {
        $sql_part1 = " update `{$table}` set ";
        $sql_part2 = " where `{$case_field}` in (";
        $sql_part3 = ')';
        $index = 0;
        $params = [];
        $sql_whens = [];
        $sql_ins = [];
        $batch_size = 500;
        foreach ($values as $case_value => $update_values) {
            foreach ($update_values as $update_index => $update_value) {
                $sql_whens[$update_index][] = " when :w{$index}_{$update_index} then :v{$index}_{$update_index}";
                $params[':w' . $index . '_' . $update_index] = $case_value;
                $params[':v' . $index . '_' . $update_index] = $update_value;
            }
            $sql_ins[] = ':i' . $index;
            $params[':i' . $index] = $case_value;
            ++$index;
            if ($index == $batch_size) {
                $sql = $sql_part1;
                foreach ($sql_whens as $key => $sql_when) {
                    $sql .= ' ' . $update_fields[$key] . ' = CASE ' . $case_field;
                    $sql .= join('', $sql_when);
                    $sql .= ' END,';
                }
                $sql = rtrim($sql, ',');
                $sql .= $sql_part2 . join(',', $sql_ins) . $sql_part3;
                if (!$db->statement($sql, $params)) {
                    throw new \RuntimeException('failed to execute sql ' . $sql);
                    break;
                }
                $index = 0;
                $params = [];
                $sql_whens = [];
                $sql_ins = [];
            }
        }

        if (!empty($sql_whens)) {
            $sql = $sql_part1;
            foreach ($sql_whens as $key => $sql_when) {
                $sql .= ' ' . $update_fields[$key] . ' = CASE ' . $case_field;
                $sql .= join('', $sql_when);
                $sql .= ' END,';
            }
            $sql = rtrim($sql, ',');
            $sql .= $sql_part2 . join(',', $sql_ins) . $sql_part3;
            if (!$db->statement($sql, $params)) {
                throw new \RuntimeException('failed to execute sql ' . $sql);
            }
        }
    }

    /**
     * 批量插入或者更新数据.
     * @param ConnectionInterface $db 数据库连接
     * @param string $table 表名
     * @param string[] $fields
     * @param array[] $rows
     */
    public static function batchInsertOnDuplicateUpdate(
        ConnectionInterface $db,
        string $table,
        array $fields,
        array $rows
    ) {
        if (empty($rows) || empty($fields)) {
            return;
        }
        $batch_size = 500;
        foreach ($fields as $field) {
            $fields_for_insert[] = "`{$field}`";
        }
        $sql_part1 = "insert into `{$table}` (" . join(',', $fields_for_insert) . ') values ';
        foreach ($fields as $field) {
            $fields_for_updates[] = "`{$field}`=values(`{$field}`)";
        }
        $sql_part2 = ' on duplicate key update ' . join(',', $fields_for_updates);
        $index = 0;
        $params = [];
        $sql_values = [];
        foreach ($rows as $row) {
            $row_values = [];
            for ($i = 0; $i < count($fields); ++$i) {
                $param = ":v{$index}_{$i}";
                $row_values[] = $param;
                $params[$param] = $row[$i];
            }
            $sql_values[] = '(' . join(',', $row_values) . ')';
            ++$index;
            if ($index == $batch_size) {
                $sql = $sql_part1 . join(',', $sql_values) . $sql_part2;
                if (!$db->statement($sql, $params)) {
                    throw new \RuntimeException('failed to execute sql ' . $sql);
                }
                $index = 0;
                $params = [];
                $sql_values = [];
            }
        }
        if (!empty($sql_values)) {
            $sql = $sql_part1 . join(',', $sql_values) . $sql_part2;
            if (!$db->statement($sql, $params)) {
                throw new \RuntimeException('failed to execute sql ' . $sql);
            }
        }
    }

    /**
     * 主动释放当前协程环境下的数据库连接
     * 用于避免耗时操作引起连接未及时回收导致连接池被用完.
     */
    public static function releaseAllConnections()
    {
        $resolver = ApplicationContext::getContainer()->get(ConnectionResolverInterface::class);
        if ($resolver instanceof ConnectionResolver) {
            $resolver->releaseAllConnections();
        }
    }

    /**
     * 关闭所有数据库链接的事件分发，在命令行模式下减少内存占用.
     */
    public static function unsetEventDispatcher()
    {
        $config = ApplicationContext::getContainer()->get(ConfigInterface::class);
        foreach ($config->get('databases') as $pool => $db_config) {
            try {
                Db::connection($pool)->unsetEventDispatcher();
            } catch (\Throwable $e) {
                LogUtil::logThrowable($e);
            }
        }
    }
}
