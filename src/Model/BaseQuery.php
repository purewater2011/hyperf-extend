<?php

declare(strict_types=1);
/**
 * This file is part of hyperf server projects.
 */
namespace Hyperf\Extend\Model;

use Hyperf\Utils\Str;

abstract class BaseQuery
{
    use ParamAndPostProcessorTrait;

    protected $field_types = [];

    protected $alerts = [];

    /**
     * 分页加载数据的情况下，统计查询结果的行数，默认不支持
     */
    abstract public function count(array $params): int;

    /**
     * 执行数据查询.
     */
    public function run(array $params): CsvTableModel
    {
        if (!empty($this->param_processor)) {
            $params = call_user_func($this->param_processor, $params);
        }
        $csv = $this->runQuery($params);
        $csv->appendColumnTypes($this->getFieldTypes());
        if (!empty($this->post_processor)) {
            $csv = call_user_func($this->post_processor, $csv) ?: $csv;
        }
        return $csv;
    }

    public function getFieldTypes()
    {
        return $this->field_types;
    }

    public function formatSql(string $sql, array $params): array
    {
        $bindings = [];
        $sql_formatted = $sql;
        $param_search_offset = 0;
        while (true) {
            preg_match('#\$\{[^\}]+\}#', $sql_formatted, $matches, PREG_OFFSET_CAPTURE, $param_search_offset);
            if (empty($matches)) {
                break;
            }
            $param_name = substr($matches[0][0], 2, -1);
            $param_search_offset = $matches[0][1] + strlen($matches[0][0]);
            [$condition_start, $condition_end] = $this->findSqlConditionStartEnd($sql_formatted, $matches[0][1]);

            // 把不存在的参数筛选条件修改为 1
            if (!isset($params[$param_name]) || is_null($params[$param_name]) || $params[$param_name] === ''
                || (is_array($params[$param_name]) && empty($params[$param_name]))) {
                $sql_formatted = substr($sql_formatted, 0, $condition_start) . ' 1=1 ' . substr($sql_formatted, $condition_end);
                $param_search_offset = $condition_start;
            }
        }

        foreach ($params as $name => $value) {
            $param_name_in_sql = ':' . $name;
            $has_null_value = false;
            if (is_array($value)) {
                if (empty($value)) {
                    // 数组筛选传入了一个空数组，那么把参数筛选的值设定为 null
                    $sql_formatted = str_replace('${' . $name . '}', 'null', $sql_formatted, $replaced_count);
                    continue;
                }
                $param_names = [];
                foreach ($value as $i => $item) {
                    if (is_null($item)) {
                        $has_null_value = true;
                    } else {
                        $param_names[] = ':' . $name . '_' . $i;
                    }
                }
                $param_name_in_sql = empty($param_names) ? '' : join(',', $param_names);
            }
            // 对于数组中含有 null 的情况做兼容，sql 中的 in 查询是不支持 null 的，把这个 in 转换为 is null 和 in
            if ($has_null_value) {
                $offset = 0;
                while (true) {
                    $offset = strpos($sql_formatted, '${' . $name . '}', $offset);
                    if ($offset === false) {
                        break;
                    }
                    [$condition_start, $condition_end] = $this->findSqlConditionStartEnd($sql_formatted, $offset);
                    $condition_sql = substr($sql_formatted, $condition_start, $condition_end - $condition_start);
                    $condition_sql = trim($condition_sql);
                    if (preg_match('#^([^ ]+) +in *\([^\)]+\)$#', $condition_sql, $matches)) {
                        if (empty($param_name_in_sql)) {
                            // 只有一个 null 匹配
                            $new_condition_sql = $matches[1] . ' is null';
                        } else {
                            // 除了 null 匹配还存在其他值匹配
                            $new_condition_sql = '(' . $condition_sql . ' or ' . $matches[1] . ' is null)';
                        }

                        $sql_formatted = substr($sql_formatted, 0, $condition_start)
                            . $new_condition_sql
                            . substr($sql_formatted, $condition_end);
                    } else {
                        throw new \RuntimeException('unsupported sql condition with null query: ' . $condition_sql);
                    }
                    $offset = $condition_end;
                }
            }

            // 执行 sql 中的参数替换
            $sql_formatted = str_replace('${' . $name . '}', $param_name_in_sql, $sql_formatted, $replaced_count);
            if ($replaced_count > 0) {
                if (is_array($value)) {
                    foreach ($value as $i => $item) {
                        if (!is_null($item)) {
                            $bindings[':' . $name . '_' . $i] = $value[$i];
                        }
                    }
                } else {
                    $bindings[':' . $name] = $value;
                }
            }
        }

        // 对直接使用 where param=:param 这种格式的 sql 传入进行支持
        if (preg_match_all('#:[a-zA-Z0-9_]+#', $sql, $matches)) {
            $param_names_in_current_sql = $matches[0];
            foreach ($params as $k => $v) {
                $param_name_in_sql = Str::startsWith($k, ':') ? $k : (':' . $k);
                if (!isset($bindings[$param_name_in_sql]) && in_array($param_name_in_sql, $param_names_in_current_sql)) {
                    $bindings[$param_name_in_sql] = $v;
                }
            }
        }

        $sql_formatted = $this->formatUdf($sql_formatted);
        $sql_formatted = preg_replace('# +#', ' ', $sql_formatted);
        $sql_formatted = preg_replace('# or 1=1#', ' ', $sql_formatted);

        // TODO: 对字符串模糊匹配做支持
        return [$sql_formatted, $bindings];
    }

    public function getAlerts()
    {
        return $this->alerts;
    }

    /**
     * 找出 sql 里面某一个字符串位置下所对应的 where 条件的起始结束位置.
     * @return int[]
     */
    protected function findSqlConditionStartEnd(string $sql, int $offset)
    {
        // 查出当前这个查询条件的 sql 起始位置
        $condition_start = false;
        foreach ($this->decorateNeedlesWithSpace(['and', 'where', 'or']) as $needle) {
            $pos = strrpos(substr($sql, 0, $offset), $needle);
            if ($pos === false) {
                continue;
            }
            $condition_start = max($condition_start, $pos + strlen($needle));
        }
        if ($condition_start === false) {
            return [false, false];
        }
        // 查出当前这个查询条件的 sql 结束位置
        $condition_end = strlen($sql);
        foreach ($this->getSqlConditionEndSearchString() as $needle) {
            $pos = strpos($sql, $needle, $offset);
            if ($pos === false) {
                continue;
            }
            $condition_end = min($condition_end, $pos);
        }

        // 通过括号匹配来定位查询条件的 sql 结束位置，如果这一个条件后面并没有跟着 and, group, limit 之类的结束标识的话
        $condition_str = substr($sql, $condition_start, $condition_end - $condition_start);
        $left_brackets_count = substr_count($condition_str, '(');
        $right_brackets_count = substr_count($condition_str, ')');
        for ($i = $right_brackets_count; $i > $left_brackets_count; --$i) {
            for ($j = $condition_end - 1; $j > $condition_start; --$j) {
                if ($sql[$j] === ')') {
                    $condition_end = $j;
                    break;
                }
            }
        }
        return [$condition_start, $condition_end];
    }

    abstract protected function runQuery(array $params): CsvTableModel;

    protected function convertRowsToCsv(array $rows): CsvTableModel
    {
        $csv = new CsvTableModel();
        foreach ($rows as $row) {
            if (empty($csv->headers)) {
                $csv->headers = array_keys($row);
            }
            $row_values = [];
            foreach ($row as $k => $v) {
                $int_field_types = [
                    'UInt8', 'UInt16', 'UInt32', 'UInt64',
                    'Nullable(UInt8)', 'Nullable(UInt16)', 'Nullable(UInt32)', 'Nullable(UInt64)',
                ];
                $float_field_types = [
                    'NEWDECIMAL',
                ];
                if (is_string($v) && in_array($this->field_types[$k], $int_field_types)) {
                    $v = intval($v);
                } elseif (is_string($v) && in_array($this->field_types[$k], $float_field_types)) {
                    $v = floatval($v);
                }
                $row_values[] = $v;
            }
            $csv->rows[] = $row_values;
        }
        return $csv;
    }

    protected function getSqlConditionEndSearchString(): array
    {
        return array_merge(
            $this->decorateNeedlesWithSpace(['group', 'order', 'limit', 'and', 'where']),
            [') as ', ") as\n", ") as\t"]
        );
    }

    /**
     * 为标识添加空格和换行符.
     */
    protected function decorateNeedlesWithSpace(array $needles): array
    {
        $res = [];
        foreach ($needles as $needle) {
            $res[] = " {$needle} ";
            $res[] = "\n{$needle} ";
            $res[] = " {$needle}\n";
            $res[] = "\n{$needle}\n";
        }
        return $res;
    }

    private function findNextFunctionCall(string $sql, string $function)
    {
        $len = strlen($sql);
        $start = stripos($sql, $function . '(');
        if ($start === false) {
            return [false, false, false];
        }
        $brackets_left = 0;
        for ($i = $start; $i < $len; ++$i) {
            if ($sql[$i] === '(') {
                ++$brackets_left;
            } elseif ($sql[$i] === ')') {
                --$brackets_left;
                if ($brackets_left === 0) {
                    $function_inner_content = substr($sql, $start + strlen($function) + 1, $i - $start - strlen($function) - 1);
                    return [$start, $i, $function_inner_content];
                }
            }
        }
        throw new \RuntimeException('failed to match udf function ' . $function);
    }
}
