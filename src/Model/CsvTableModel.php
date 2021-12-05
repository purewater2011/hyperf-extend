<?php

declare(strict_types=1);
/**
 * This file is part of hyperf server projects.
 */
namespace Hyperf\Extend\Model;

use Hyperf\Utils\Str;
use Hyperf\Extend\Utils\ArrayUtil;
use Hyperf\Extend\Utils\File;

class CsvTableModel
{
    /**
     * 默认合并模式，用新的替换老的.
     */
    const MERGE_TYPE_DEFAULT = 0;

    /**
     * 默认合并模式，取最大值
     */
    const MERGE_TYPE_MAX = 1;

    /**
     * 默认合并模式，取最小值
     */
    const MERGE_TYPE_MIN = 2;

    /**
     * 默认合并模式，求和.
     */
    const MERGE_TYPE_SUM = 3;

    public $headers = [];

    public $rows = [];

    private $column_types = [];

    /**
     * 获取某一个单元格的值
     * @param array|int $row 支持传入这一行的所有值的数组，也支持传入行编号(0为第一行)
     * @param int|string $column 支持传入列名称或者列编号(0为第一列)
     * @return mixed
     */
    public function cell($row, $column)
    {
        $column = $this->getColumnIndex($column);
        if (is_int($row)) {
            $row = $this->rows[$row];
        }
        if (!is_array($row) || !is_int($column)) {
            throw new \RuntimeException('param not expected');
        }
        return $row[$column];
    }

    /**
     * 获取某一列的所有行.
     * @param int|string $column 支持传入列名称或者列编号(0为第一列)
     */
    public function column($column): array
    {
        $values = [];
        $column = $this->getColumnIndex($column);
        foreach ($this->rows as $i => $row) {
            $values[] = $row[$column];
        }
        return $values;
    }

    /**
     * 在某一列中找某一个值位于第几行.
     * @param int|string $column
     * @param $value
     * @return false|int
     */
    public function findInColumn($column, $value)
    {
        $column = $this->getColumnIndex($column);
        foreach ($this->rows as $i => $row) {
            if ($row[$column] === $value) {
                return $i;
            }
        }
        return false;
    }

    /**
     * 把另外一个 csv 数据合并到本实例.
     */
    public function merge(CsvTableModel $another, int $merge_type = self::MERGE_TYPE_DEFAULT, int $group_column_number = 1)
    {
        $this->appendColumnTypes($another->column_types);
        //如果当前列表空，将要合并数据赋值给当前后返回,不再走下面的合并逻辑，防止赋值2次，数值翻倍
        if (empty($this->headers)) {
            $this->headers = $another->headers;
            $this->rows = $another->rows;
            return;
        }
        if (empty($another->headers)) {
            return;
        }
        for ($i = 0; $i < $group_column_number; ++$i) {
            if ($this->headers[$i] !== $another->headers[$i]) {
                // 两个 csv 表格合并时，要求前{$group_column_number}列名称一定相同，这样才能够执行数据合并
                throw new \RuntimeException('两个 csv 表格的第' . ($i + 1) . '列名称不相同，无法合并');
            }
        }

        // 合并表头
        foreach ($another->headers as $header) {
            if (in_array($header, $this->headers)) {
                continue;
            }
            $this->headers[] = $header;
            foreach ($this->rows as $i => $row) {
                $this->rows[$i][] = null;
            }
        }

        if (empty($another->rows)) {
            // 要合并进来的数据库表没有数据
            return;
        }
        foreach ($another->rows as $i => $row) {
            $row_in_this_model = $this->findGroupColumnInRows($group_column_number, $row);
            if ($row_in_this_model === false) {
                $row_in_this_model = count($this->rows);
                $this->rows[] = array_fill(0, count($this->headers), null);
                for ($j = 0; $j < $group_column_number; ++$j) {
                    $this->rows[$row_in_this_model][$j] = $row[$j];
                }
            }
            foreach ($row as $j => $value) {
                if ($j < $group_column_number || is_null($value)) {
                    continue;
                }
                $column_in_this_model = $this->getColumnIndex($another->headers[$j]);
                $value_in_this_model = $this->rows[$row_in_this_model][$column_in_this_model];
                $this->rows[$row_in_this_model][$column_in_this_model] = $this->calculateAggregateValue(
                    $value_in_this_model,
                    $value,
                    $merge_type
                );
            }
        }
    }

    /**
     * 把某一列的值提取出来作为新的列进行扩展，用法参考单元测试.
     * @param string $expand_column 要进行扩展的列
     * @param string $aggregate_column 要进行聚合计算的列
     * @param int $aggregate_method 聚合的计算方式
     */
    public function expand(string $expand_column, string $aggregate_column, int $aggregate_method = self::MERGE_TYPE_SUM)
    {
        if (empty($this->headers)) {
            return;
        }
        $expand_column_index = $this->getColumnIndex($expand_column);
        $aggregate_column_index = $this->getColumnIndex($aggregate_column);
        $old_headers = $this->headers;
        $old_rows = $this->rows;
        $new_headers = [];
        foreach ($this->headers as $header) {
            if ($header === $expand_column) {
                continue;
            }
            if ($header === $aggregate_column) {
                continue;
            }
            $new_headers[] = $header;
        }

        // 获取出将扩展出的新的列的信息
        $expanded_headers = [];
        foreach ($old_rows as $row) {
            $header = $row[$expand_column_index];
            if (!isset($expanded_headers[$header])) {
                $new_headers[] = $header;
                $expanded_headers[$header] = count($new_headers) - 1;
            }
        }
        $this->headers = $new_headers;

        // 重新构建新的行数据
        $new_rows_map = [];
        foreach ($old_rows as $i => $old_row) {
            $old_values_left = [];
            foreach ($old_headers as $j => $old_header) {
                if ($old_header === $expand_column) {
                    continue;
                }
                if ($old_header === $aggregate_column) {
                    continue;
                }
                $old_values_left[] = $old_row[$j];
            }
            $new_row_key = join(chr(0x01), $old_values_left);
            if (!isset($new_rows_map[$new_row_key])) {
                $new_rows_map[$new_row_key] = $old_values_left;
                foreach ($expanded_headers as $header => $header_index) {
                    $new_rows_map[$new_row_key][] = null;
                }
            }
            $expand_column_value = $old_row[$expand_column_index];
            $current_value_index = $expanded_headers[$expand_column_value];
            $aggregate_column_value = $old_row[$aggregate_column_index];
            $new_rows_map[$new_row_key][$current_value_index] = $this->calculateAggregateValue(
                $new_rows_map[$new_row_key][$current_value_index],
                $aggregate_column_value,
                $aggregate_method
            );
        }
        $this->rows = array_values($new_rows_map);
    }

    /**
     * 往表格中增加一列.
     * @param string $header 该列的表头
     * @param callable $callback 每一行的回调处理函数，函数声明 function(array $row){}
     * @param null|int $index 新的列要加载哪个位置上
     */
    public function addColumn(string $header, callable $callback, ?int $index = null)
    {
        foreach ($this->rows as $i => $row) {
            $this->mapRowWithHeaders($row);
            $value = call_user_func($callback, $row);
            if ($index === null) {
                $this->rows[$i][] = $value;
            } else {
                array_splice($this->rows[$i], $index, 0, [$value]);
            }
        }
        // 先修改每一行的数据内容，然后再修改 header，否则 callback 中通过 cell 函数可能获取到错误的单元格数据
        if ($index === null) {
            $this->headers[] = $header;
        } else {
            $index = $index >= 0 ? $index : count($this->headers) + $index;
            array_splice($this->headers, $index, 0, [$header]);
        }
    }

    /**
     * 增加一列，列值为多列的总和相加.
     * @param string $header 该列的表头
     * @param array $sum_columns 要执行相加的列
     * @param null|int $index 新的列要加载哪个位置上
     */
    public function addColumnSum(string $header, array $sum_columns, ?int $index = null)
    {
        $this->addColumn($header, function ($row) use ($sum_columns) {
            $value = 0;
            foreach ($sum_columns as $column_key) {
                $value += $row[$column_key] ?? 0;
            }
            return $value;
        }, $index);
    }

    /**
     * 增加一列，列值为其他两列计算出来的百分比.
     * @param string $header 该列的表头
     * @param int|string $numerator 分子列
     * @param int|string $denominator 分母列
     * @param null|int $index 新的列要加载哪个位置上
     */
    public function addColumnPercentage(string $header, $numerator, $denominator, ?int $index = null)
    {
        try {
            $this->addColumn($header, function ($row) use ($numerator, $denominator) {
                if (empty($row[$numerator]) || empty($row[$denominator])) {
                    return null;
                }
                return sprintf('%.2f%%', $row[$numerator] * 100 / $row[$denominator]);
            }, $index);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * 增加一列，列值为此单元格值占整列总和的百分比.
     * @param string $header 该列的表头
     * @param string $calculate_column 要执行计算的列
     * @param null|int $index 新的列要加载哪个位置上
     */
    public function addSingleColumnPercentage(string $header, string $calculate_column, ?int $index = null)
    {
        $sum_value = 0;
        $column_index = $this->getColumnIndex($calculate_column);
        foreach ($this->rows as $row) {
            $sum_value += $row[$column_index];
        }
        $this->addColumn($header, function ($row) use ($calculate_column, $sum_value) {
            if (empty($calculate_column) || empty($sum_value)) {
                return null;
            }
            return sprintf('%.2f%%', $row[$calculate_column] * 100 / $sum_value);
        }, $index);
    }

    /**
     * 复制一列.
     * @param string $header 该列的表头
     * @param int|string $source 要复制的源头列
     * @param null|int $index 新的列要加载哪个位置上
     */
    public function duplicateColumn(string $header, $source, ?int $index = null)
    {
        $source_index = $this->getColumnIndex($source);
        $this->addColumn($header, function ($row) use ($source_index) {
            return $row[$source_index];
        }, $index);
    }

    /**
     * 替换一列的值
     * @param int|string $column 要替换的列
     * @param callable $callback 每一行的回调处理函数，函数声明 function($cell_value, array $row, int $column_index){}
     */
    public function replaceColumn($column, callable $callback)
    {
        if (is_string($column) && !$this->existColumn($column)) {
            // 忽略列不存在的错误
            return;
        }
        $column_index = $this->getColumnIndex($column);
        foreach ($this->rows as $i => $row) {
            $this->mapRowWithHeaders($row);
            $cell_value = $row[$column_index];
            $this->rows[$i][$column_index] = call_user_func($callback, $cell_value, $row, $column_index);
        }
    }

    /**
     * 使用一个 map 来替换某一列的值
     * @param int|string $column 该列的表头
     * @param array $value_map 值映射
     */
    public function replaceColumnWithValueMap($column, array $value_map)
    {
        $this->replaceColumn($column, function ($cell_value) use ($value_map) {
            return $value_map[$cell_value] ?? null;
        });
    }

    /**
     * 设置列的默认值
     * @param int|string $column
     * @param int|string $default
     */
    public function setColumnDefaultValue($column, $default = '')
    {
        $column_index = $this->getColumnIndex($column);
        foreach ($this->rows as $i => $row) {
            if (!isset($row[$column_index])) {
                $this->rows[$i][$column_index] = $default;
            }
        }
    }

    /**
     * 采用某一个列对表格做排序.
     * @param array|int|string $column
     * @param bool $reverse 是否采用倒序排序
     */
    public function sortByColumn($column, $reverse = false)
    {
        $sort_columns = is_array($column) ? $column : [$column];
        $sort_column_indexes = [];
        foreach ($sort_columns as $c) {
            try {
                $sort_column_indexes[] = $this->getColumnIndex($c);
            } catch (\Throwable $e) {
            }
        }
        usort($this->rows, function ($a, $b) use ($sort_column_indexes, $reverse) {
            foreach ($sort_column_indexes as $column) {
                if ($a[$column] === $b[$column]) {
                    continue;
                }
                if (is_numeric($a[$column]) || is_numeric($b[$column])) {
                    $a[$column] = intval($a[$column]);
                    $b[$column] = intval($b[$column]);
                }
                if ($a[$column] < $b[$column]) {
                    return $reverse ? 1 : -1;
                }
                return $reverse ? -1 : 1;
            }
            return 0;
        });
    }

    /**
     * 把表头显示使用一个数组进行替换.
     */
    public function replaceHeaders(array $new_headers_map)
    {
        foreach ($this->headers as $i => $header) {
            $this->headers[$i] = $new_headers_map[$header] ?? $header;
        }
    }

    /**
     * 删除某一行.
     */
    public function removeRow(int $row)
    {
        array_splice($this->rows, $row, 1);
    }

    /**
     * 删除某一列.
     * @param int|string $column
     */
    public function removeColumn($column)
    {
        try {
            $column = $this->getColumnIndex($column);
        } catch (\RuntimeException $exception) {
            return;
        }
        array_splice($this->headers, $column, 1);
        foreach ($this->rows as $i => $row) {
            array_splice($this->rows[$i], $column, 1);
        }
    }

    /**
     * 交换两列顺序.
     * @param int|string $column1
     * @param int|string $column2
     */
    public function swapColumns($column1, $column2)
    {
        $column1_index = $this->getColumnIndex($column1);
        $column2_index = $this->getColumnIndex($column2);
        ArrayUtil::swap($this->headers, $column1_index, $column2_index);
        foreach ($this->rows as $i => $row) {
            ArrayUtil::swap($this->rows[$i], $column1_index, $column2_index);
        }
    }

    /**
     * 把某一列移动到某个位置上去.
     * @param int|string $column 要执行移动的列
     * @param int $index 要放置的第几列的索引值
     */
    public function moveColumn($column, int $index)
    {
        $current_index = $this->getColumnIndex($column);
        if ($current_index === $index) {
            return;
        }
        // 移动列头
        $current_header = $this->headers[$current_index];
        array_splice($this->headers, $current_index, 1);
        array_splice($this->headers, $index, 0, [$current_header]);
        // 移动所有行
        foreach ($this->rows as $i => $row) {
            $current_value = $row[$current_index] ?? null;
            array_splice($this->rows[$i], $current_index, 1);
            array_splice($this->rows[$i], $index, 0, [$current_value]);
        }
    }

    /**
     * 判断某列是否存在.
     * @param $column
     * @return bool
     */
    public function existColumn($column)
    {
        return array_search($column, $this->headers) !== false;
    }

    /**
     * 根据列的名字获取该列的索引.
     * @param $column
     * @return int
     */
    public function getColumnIndex($column)
    {
        if (is_string($column)) {
            $column = array_search($column, $this->headers);
            if ($column === false) {
                throw new \RuntimeException('cannot find field ' . $column . ' in table');
            }
        }
        return $column;
    }

    /**
     * 给某列加跳转链接.
     * @param int|string $text_column 需要显示的列
     * @param string $url_prefix 地址前缀
     * @param int|string $id_column 后缀id
     * @deprecated 展示格式化功能需要整体设计
     */
    public function addLink($text_column, string $url_prefix, $id_column)
    {
        $text_column_index = $this->getColumnIndex($text_column);
        $id_column_index = $this->getColumnIndex($id_column);
        $map = [];
        foreach ($this->rows as $i => $row) {
            $map[$row[$text_column_index]] = $url_prefix . $row[$id_column_index] . "\t" . $row[$text_column_index];
        }
        $this->replaceColumnWithValueMap($text_column_index, $map);
    }

    /**
     * 给某列加编辑功能.
     * @param int|string $text_column 需要显示的列
     * @param string $database 数据库名称
     * @param string $table 表名
     * @param string $field 字段名
     */
    public function addEdit($text_column, $database, $table, $field)
    {
        $text_column_index = $this->getColumnIndex($text_column);
        $map = [];
        foreach ($this->rows as $i => $row) {
            $map[$row[$text_column_index]] = "edit\t" . "{$database}\t" . "{$table}\t" . "{$field}\t" . $row[$text_column_index];
        }
        $this->replaceColumnWithValueMap($text_column_index, $map);
    }

    public function appendColumnTypes(array $column_types)
    {
        $this->column_types = array_merge($this->column_types, $column_types);
    }

    public function getColumnTypes()
    {
        return $this->column_types;
    }

    /**
     * 保存 csv 至文件.
     */
    public function saveToFile(string $file_path, bool $with_header = true)
    {
        $is_gz = Str::endsWith($file_path, '.gz');
        $handle = $is_gz ? gzopen($file_path, 'w') : fopen($file_path, 'w');
        $is_gz ? gzwrite($handle, "\xEF\xBB\xBF") : fwrite($handle, "\xEF\xBB\xBF");
        if ($with_header) {
            foreach ($this->headers as $i => $header) {
                if ($i > 0) {
                    $is_gz ? gzwrite($handle, ',') : fwrite($handle, ',');
                }
                $header = str_replace(',', '\,', $header);
                $is_gz ? gzwrite($handle, $header) : fwrite($handle, $header);
            }
        }
        $is_gz ? gzwrite($handle, "\n") : fwrite($handle, "\n");
        foreach ($this->rows as $row) {
            foreach ($row as $i => $item) {
                if ($i > 0) {
                    $is_gz ? gzwrite($handle, ',') : fwrite($handle, ',');
                }
                $item = strval($item);

                // 处理CSV特殊字符
                if (Str::contains($item, ',') || Str::contains($item, "\n") || Str::contains($item, '"')) {
                    $item = str_replace('"', '""', $item);
                    $item = '"' . $item . '"';
                }

                $is_gz ? gzwrite($handle, $item) : fwrite($handle, $item);
            }
            $is_gz ? gzwrite($handle, "\n") : fwrite($handle, "\n");
        }
        $is_gz ? gzclose($handle) : fclose($handle);
    }

    /**
     * 按照分组字段找某一行位于第几行.
     * @param int $group_column_number
     * @param array $search_row
     * @return false|int
     */
    private function findGroupColumnInRows($group_column_number, $search_row)
    {
        foreach ($this->rows as $i => $row) {
            if (array_slice($row, 0, $group_column_number) == array_slice($search_row, 0, $group_column_number)) {
                return $i;
            }
        }
        return false;
    }

    private function calculateAggregateValue($v1, $v2, int $aggregate_method)
    {
        if (is_null($v1)) {
            return $v2;
        }
        switch ($aggregate_method) {
            case self::MERGE_TYPE_MAX:
                return max($v1, $v2);
            case self::MERGE_TYPE_MIN:
                return min($v1, $v2);
            case self::MERGE_TYPE_SUM:
                return $v1 + $v2;
            case self::MERGE_TYPE_DEFAULT:
                return $v2;
            default:
                throw new \RuntimeException("aggregate method {$aggregate_method} is not implemented");
        }
    }

    /**
     * 根据一批列的名字获取这些列的索引.
     * @return int[]
     */
    private function getColumnIndexes(array $columns)
    {
        $column_indexes = [];
        foreach ($columns as $column) {
            $column_indexes[] = $this->getColumnIndex($column);
        }
        return $column_indexes;
    }

    /**
     * 将header作为key赋值给行数据.
     */
    private function mapRowWithHeaders(array &$row): array
    {
        foreach ($row as $i => $value) {
            $row[$this->headers[$i]] = $value;
        }
        return $row;
    }
}
