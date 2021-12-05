<?php

declare(strict_types=1);
/**
 * This file is part of hyperf server projects.
 */
namespace Hyperf\Extend\Model;

use Hyperf\Database\Connection;
use Hyperf\Database\Query\Builder;
use Hyperf\HttpMessage\Exception\HttpException;
use Hyperf\Utils\Collection;

class LocalDataBuilder extends Builder
{
    /**
     * @var Connection
     */
    public $connection;

    /**
     * @var array[]
     */
    protected $data_source = [];

    public function __construct(array $data_source)
    {
        $this->data_source = $data_source;
        $connection = new Connection(function () {
        });
        parent::__construct($connection);
    }

    public function get($columns = ['*']): Collection
    {
        $this->select($columns);
        return collect($this->selectFromDataSource());
    }

    /**
     * 从本地数据中查询记录.
     * @return array[]
     */
    protected function selectFromDataSource(): array
    {
        $data = $this->filterByWheres($this->data_source);
        $data = $this->sortByOrders($data);
        $data = array_slice($data, $this->offset ?? 0, $this->limit);
        return $this->mapBySelects($data);
    }

    /**
     * 执行查询条件.
     * @param array[] $data
     * @return array[]
     */
    protected function filterByWheres(array $data): array
    {
        $filtered_data = [];
        foreach ($data as $id => $row) {
            $row['id'] = $row['id'] ?? $id;
            if ($this->meetWhereCondition($this->wheres, $row)) {
                $filtered_data[] = $row;
            }
        }
        return $filtered_data;
    }

    /**
     * 执行排序.
     * @param array[] $data
     * @return array[]
     */
    protected function sortByOrders(array $data): array
    {
        if ($this->orders) {
            usort($data, function ($row1, $row2) {
                foreach ($this->orders as $order) {
                    $column = $this->clearTableName($order['column']);
                    $direction = $order['direction'];
                    $column1 = $row1[$column];
                    $column2 = $row2[$column];
                    if ($column1 == $column2) {
                        continue;
                    }
                    $is_larger = $column1 > $column2 ? 1 : -1;
                    if ($direction === 'desc') {
                        $is_larger *= -1;
                    }
                    return $is_larger;
                }
                return 0;
            });
        }
        return $data;
    }

    /**
     * 执行字段筛选.
     * @param array[] $data
     * @return array[]
     */
    protected function mapBySelects(array $data): array
    {
        return array_map(function ($row) {
            $selected_item = [];
            foreach ($this->columns as $column) {
                if ($column === '*') {
                    $selected_item = array_merge($selected_item, $row);
                } else {
                    $selected_item[$column] = $row[$column] ?? null;
                }
            }
            return $selected_item;
        }, $data);
    }

    /**
     * 测试是否满足查询条件.
     * @param array[] $wheres 查询条件
     * @param array $row 单行数据
     */
    protected function meetWhereCondition(array $wheres, array $row): bool
    {
        $response = true;
        foreach ($wheres as $condition) {
            $method = 'assert' . $condition['type'];
            if (!method_exists($this, $method)) {
                throw new HttpException(500, 'LocalData模型不支持' . $condition['type'] . '类型查询');
            }
            // 根据type调用不同的assert方法
            $assert_result = $this->{$method}($condition, $row);
            $is_or_where = $condition['boolean'] === 'or';
            if ($is_or_where && $assert_result) {
                return true;
            }
            if (!$is_or_where) {
                $response = $response && $assert_result;
            }
        }
        return $response;
    }

    /*  处理不同类型的where条件  */

    /**
     * where条件判断.
     * @param $condition
     * @param $data
     */
    protected function assertBasic($condition, $data): bool
    {
        $res = false;
        $value = $this->fetchDataValue($data, $condition['column']);
        if (isset($value)) {
            $operator = $condition['operator'];
            $expected = $this->fetchConditionValue($condition);
            $res = $this->checkByOperator($value, $operator, $expected);
        }
        return $res;
    }

    /**
     * whereIn条件判断.
     * @param $condition
     * @param $data
     */
    protected function assertIn($condition, $data): bool
    {
        $value = $this->fetchDataValue($data, $condition['column']);
        $expected = $this->fetchConditionValue($condition);
        return in_array($value, $expected);
    }

    /**
     * whereNotIn条件判断.
     * @param $condition
     * @param $data
     */
    protected function assertNotIn($condition, $data): bool
    {
        return !$this->assertIn($condition, $data);
    }

    /**
     * whereNull条件判断.
     * @param $condition
     * @param $data
     */
    protected function assertNull($condition, $data): bool
    {
        $value = $this->fetchDataValue($data, $condition['column']);
        return $value === null;
    }

    /**
     * whereNotNull条件判断.
     * @param $condition
     * @param $data
     */
    protected function assertNotNull($condition, $data): bool
    {
        return !$this->assertNull($condition, $data);
    }

    /**
     * 条件组合，where中传入闭包函数.
     * @param $condition
     * @param $data
     */
    protected function assertNested($condition, $data): bool
    {
        return $this->meetWhereCondition($condition['query']->wheres, $data);
    }

    /*  处理不同类型的where条件 End  */

    /**
     * 获取条件值
     * @param array $condition
     * @return mixed
     */
    protected function fetchConditionValue($condition)
    {
        $value = $condition['value'];
        if ($value instanceof \DateTimeInterface) {
            $value = $value->format($this->grammar->getDateFormat());
        } elseif (is_bool($value)) {
            $value = (int) $value;
        }
        return $value;
    }

    /**
     * 获取数据源中的值
     * @return null|mixed
     */
    protected function fetchDataValue(array $data, string $column)
    {
        $column = $this->clearTableName($column);
        // 没有设置该字段时，视为null
        return $data[$column] ?? null;
    }

    protected function checkByOperator($value, string $operator, $expected): bool
    {
        switch ($operator) {
            case '=':
            case '<=>':
                return $value == $expected;
            case '<':
                return $value < $expected;
            case '>':
                return $value > $expected;
            case '<=':
                return $value <= $expected;
            case '>=':
                return $value >= $expected;
            case '<>':
            case '!=':
                return $value != $expected;
            case 'like':
                $reg = '^' . str_replace('%', '.*', $expected) . '$';
                return (bool) preg_match($reg, $value);
            case 'not like':
                $reg = '^' . str_replace('%', '.*', $expected) . '$';
                return !preg_match($reg, $value);
            default:
                return false;
        }
    }

    /**
     * 清除字段中的表名.
     */
    protected function clearTableName(string $column): string
    {
        return str_replace($this->from . '.', '', $column);
    }
}
