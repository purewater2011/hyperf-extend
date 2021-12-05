<?php

declare(strict_types=1);
/**
 * This file is part of hyperf server projects.
 */
namespace Hyperf\Extend\Test\Listener;

use Hyperf\Database\Events\QueryExecuted;
use Hyperf\DbConnection\Db;
use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\Utils\Str;

class DbQueryExecutedListener implements ListenerInterface
{
    private $updated_tables = [];

    public function listen(): array
    {
        return [
            QueryExecuted::class,
        ];
    }

    public function process(object $event)
    {
        /* @var QueryExecuted $sql */
        if ($event instanceof QueryExecuted) {
            $sql = trim(strtolower($event->sql));
            $reg = null;
            if (Str::startsWith($sql, 'insert')) {
                $reg = '#^insert (ignore)? *(into)? *`?([^ `]+)`?#';
            } elseif (Str::startsWith($sql, 'update')) {
                $reg = '#^update (low_priority)? *(ignore)? *`?([^ `]+)`?#';
            }
            if (empty($reg)) {
            } elseif (preg_match($reg, $sql, $matches)) {
                $db_name = $event->connectionName;
                $table_name = $matches[3];
                $this->updated_tables[$db_name] = $this->updated_tables[$db_name] ?? [];
                $this->updated_tables[$db_name][$table_name] = $table_name;
            } else {
                throw new \RuntimeException('cannot find table name from sql ' . $sql);
            }
            var_dump($this->updated_tables);
        }
    }

    public function truncateAllChangedTables()
    {
        foreach ($this->updated_tables as $db_name => $table_names) {
            foreach ($table_names as $table_name) {
                Db::connection($db_name)->update('truncate ' . $table_name);
            }
        }
        $this->updated_tables = [];
    }
}
