<?php

declare(strict_types=1);
/**
 * This file is part of hyperf server projects.
 */
namespace Hyperf\Extend\Listener;

use Hyperf\Database\Events\QueryExecuted;
use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\Logger\LoggerFactory;
use Hyperf\Utils\Context;
use Hyperf\Utils\Str;
use Hyperf\Extend\Utils\ENV;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

class DbQueryExecutedListener implements ListenerInterface
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(ContainerInterface $container)
    {
        $this->logger = $container->get(LoggerFactory::class)->get('sql');
    }

    public function listen(): array
    {
        return [
            QueryExecuted::class,
        ];
    }

    /**
     * @param QueryExecuted $event
     */
    public function process(object $event)
    {
        if ($event instanceof QueryExecuted) {
            if (ENV::isPre() || ENV::isDev() || (ENV::isTest() && !ENV::isRunningUnitTests())) {
                $sql = $event->sql;
                foreach ($event->bindings as $key => $value) {
                    if (is_string($key)) { //处理:id这种类型的替换 , 优先处理:id , 否则可能误替换了第一个
                        $sql = str_replace($key, "'{$value}'", $sql);
                    } else {
                        $sql = Str::replaceFirst('?', "'{$value}'", $sql);
                    }
                }

                $sql_log = sprintf('[DB:%s] [%s] %s', $event->connectionName, $event->time, $sql);
                if (is_array($event->result)) {
                    $sql_log .= ' (' . count($event->result) . ' rows)';
                }
                // 处理 sql 日志中的换行 tab 空格等字符，压缩 sql 长度
                $sql_log = str_replace("\n", '', $sql_log);
                $sql_log = str_replace("\t", '', $sql_log);
                $sql_log = preg_replace('!\s+!', ' ', $sql_log);

                // 把 sql 记录到 Context 中，以便在接口日志记录中记录下所执行的 sql
                $sql_key = 'DB_SQL_QUEUES';
                $db_sql_queues = Context::get($sql_key, []);
                $db_sql_queues[] = $sql_log;
                Context::set($sql_key, $db_sql_queues);
                $this->logger->info($sql_log);
            }
        }
    }
}
