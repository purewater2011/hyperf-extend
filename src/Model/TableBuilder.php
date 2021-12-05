<?php

declare(strict_types=1);
/**
 * This file is part of hyperf server projects.
 */
namespace Hyperf\Extend\Model;

use Hyperf\Extend\Constant;
use Hyperf\Extend\Model\BaseQuery;
use Hyperf\Extend\Model\DbQuery;
use Hyperf\Extend\Model\ParamAndPostProcessorTrait;
use Hyperf\Extend\Model\CsvTableModel;
use Hyperf\Extend\Utils\DateUtil;
use Hyperf\Extend\Utils\Util;
use Hyperf\Extend\Utils\ClientInfoUtil;

class TableBuilder
{
    use ParamAndPostProcessorTrait;

    /**
     * @var array
     */
    private $queries;

    private $default_sort = true;

    /**
     * @var int 指定按照前几列进行分组合并
     */
    public $group_column_number = 1;

    /**
     * @var bool 是否开启了表格的数据分页
     */
    public $pager_enabled = false;

    /**
     * @var int 分页场景下的总行数
     */
    private $pager_total_count = 0;

    /**
     * @return DbQuery
     */
    public function addDbQuery(string $sql, string $pool = 'default')
    {
        $this->queries[] = new DbQuery($sql, $pool);
        return $this->queries[count($this->queries) - 1];
    }

    /**
     * @return BaseQuery[]
     */
    public function getQueries()
    {
        return $this->queries;
    }

    public function build(array $params): ?CsvTableModel
    {
        $params = $this->processParams($params);
        $csv = null;
        foreach ($this->queries as $query) {
            if ($csv === null) {
                $csv = $query->run($params);
            } else {
                $csv->merge($query->run($params), CsvTableModel::MERGE_TYPE_SUM, $this->group_column_number);
            }
        }
        // 对于第一列是日期的数据报表，默认按照日期进行倒排序
        if ($this->default_sort && !empty($csv->rows) && !empty($csv->rows[0]) && !empty($csv->rows[0][0])
            && is_string($csv->rows[0][0])
            && preg_match('#^[0-9]{4}-[0-9]{2}-[0-9]{2}#', $csv->rows[0][0])) {
            $csv->sortByColumn(0, true);
        }
        if (!empty($this->post_processor)) {
            $csv = call_user_func($this->post_processor, $csv) ?: $csv;
        }
        if ($this->pager_enabled) {
            $this->pager_total_count = $this->queries[0]->count($params);
        }
        return $csv;
    }

    /**
     * 处理参数.
     */
    public function processParams(array $params): array
    {
        if (!empty($this->param_processor)) {
            $params = call_user_func($this->param_processor, $params);
        }
        return $params;
    }

    public function getPagerTotalCount()
    {
        return $this->pager_total_count;
    }

    public function getAlerts()
    {
        $alerts = [];
        foreach ($this->queries as $query) {
            $query_alerts = $query->getAlerts();
            if (!empty($query_alerts)) {
                $alerts = array_merge($alerts, $query_alerts);
            }
        }
        return $alerts;
    }
}
