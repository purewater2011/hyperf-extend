<?php

declare(strict_types=1);
/**
 * This file is part of hyperf server projects.
 */
namespace Hyperf\Extend\Model;

use Hyperf\Database\DetectsLostConnections;
use Hyperf\DbConnection\Db;

class DbQuery extends BaseQuery
{
    use DetectsLostConnections;
    use SqlForPagerCountTrait;

    private $pool;

    private $sql;

    public function __construct(string $sql, string $pool = 'default')
    {
        $this->sql = $sql;
        $this->pool = $pool;
    }

    public function getPool(): string
    {
        return $this->pool;
    }

    public function count(array $params): int
    {
        if (empty($this->sql_for_pager_count)) {
            $this->buildSqlForPagerCount($this->sql);
        }
        [$sql, $bindings] = $this->formatSql($this->sql_for_pager_count, $params);
        return array_values(get_object_vars(Db::connection($this->pool)->selectOne($sql, $bindings)))[0];
    }

    protected function runQuery(array $params): CsvTableModel
    {
        [$sql, $bindings] = $this->formatSql($this->sql, $params);
        /** @var \Hyperf\Database\Connection $pool */
        $pool = Db::connection($this->pool);
        $run_pdo_query = function () use ($pool, $sql, $bindings): \PDOStatement {
            /** @var \PDO $pdo */
            $pdo = $pool->getReadPdo();
            $stmt = $pdo->prepare($sql);
            $stmt->execute($bindings);
            return $stmt;
        };
        try {
            $stmt = $run_pdo_query();
        } catch (\Throwable $e) {
            if ($this->causedByLostConnection($e)) {
                $pool->reconnect();
                $stmt = $run_pdo_query();
            } else {
                throw $e;
            }
        }
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        if (empty($this->field_types)) {
            for ($i = 0; $i < $stmt->columnCount(); ++$i) {
                $meta = $stmt->getColumnMeta($i);
                $this->field_types[$meta['name']] = $meta['native_type'] ?? null;
            }
        }
        $csv = $this->convertRowsToCsv($rows);
        if (empty($csv->headers)) {
            $csv->headers = array_keys($this->field_types);
        }
        return $csv;
    }
}
