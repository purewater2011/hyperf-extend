<?php

declare(strict_types=1);
/**
 * This file is part of hyperf server projects.
 */
namespace Hyperf\Extend\Model;

trait SqlForPagerCountTrait
{
    /**
     * @var string 用来执行分页数量总计的 sql
     */
    private $sql_for_pager_count;

    public function setSqlForPagerCount(string $sql)
    {
        $this->sql_for_pager_count = $sql;
    }

    private function buildSqlForPagerCount(string $original_sql)
    {
        $original_sql = trim($original_sql);
        $select_index = stripos($original_sql, 'select');
        $from_index = stripos($original_sql, 'from ', $select_index);
        $order_index = strripos($original_sql, 'order by') ?: strlen($original_sql);
        $limit_index = strripos($original_sql, 'limit ') ?: strlen($original_sql);
        $sql_count_end_index = min($order_index, $limit_index);

        // 对于 group by 进行支持
        $group_index = strripos($original_sql, 'group by');
        if ($group_index !== false) {
            $count_sql = 'select count(1) from (';
            $count_sql .= trim(substr($original_sql, 0, $sql_count_end_index));
            $count_sql .= ') TEMP';
            $this->sql_for_pager_count = trim($count_sql);
            return;
        }

        $count_sql = '';
        $count_sql .= substr($original_sql, 0, $select_index + 7);
        $count_sql .= 'count(1) ';
        $count_sql .= substr($original_sql, $from_index, $sql_count_end_index - $from_index);
        $this->sql_for_pager_count = trim($count_sql);
    }
}
