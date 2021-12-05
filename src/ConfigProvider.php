<?php

declare(strict_types=1);
/**
 * This file is part of hyperf server projects.
 */
namespace Hyperf\Extend;

use Hyperf\Extend\Exception\Formatter\ExceptionFormatter;
use Hyperf\Extend\Interfaces\IGrpcProjectCircuitBreaker;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Database\Commands\Migrations\MigrateCommand;
use Hyperf\Database\Commands\Migrations\RollbackCommand;
use Hyperf\Database\Connection;
use Hyperf\Database\ConnectionResolverInterface;
use Hyperf\Database\Connectors\ConnectionFactory;
use Hyperf\DbConnection\Pool\PoolFactory;
use Hyperf\ExceptionHandler\Formatter\FormatterInterface;
use Hyperf\Framework\Bootstrap\WorkerExitCallback;
use Hyperf\Framework\Bootstrap\WorkerStopCallback;
use Hyperf\HttpServer\ResponseEmitter;
use Hyperf\HttpServer\Router\DispatcherFactory;
use Hyperf\ModelCache\Listener\DeleteCacheListener;
use Hyperf\Redis\RedisProxy;
use Hyperf\Server\Event;
use Hyperf\Extend\Command\DataExport\MysqlCommand;
use Hyperf\Extend\Command\DataExport\RedisCommand;
use Hyperf\Extend\Command\Migrations\MigrateCommand as HyperfMigrateCommand;
use Hyperf\Extend\Command\Migrations\RollbackCommand as HyperfRollbackCommand;
use Hyperf\Extend\Listener\ConsoleCommandEventListener;
use Hyperf\Extend\Listener\DeleteCacheListener as HyperfDeleteCacheListener;
use Hyperf\Extend\Listener\OnWorkerExitListener;
use Hyperf\Extend\Listener\ResponseSendFailedOrSlowRequestListener;
use Hyperf\Extend\Middleware\AuthMiddleware;
use Hyperf\Extend\Middleware\CorsMiddleware;
use Hyperf\Extend\Middleware\SignMiddleware;
use Hyperf\Extend\Overrides\HyperfConnectionResolver as ConnectionResolver;
use Hyperf\Extend\Overrides\GrpcProjectCircuitBreaker;
use Hyperf\Extend\Overrides\HyperfRedisProxy;
use Hyperf\Extend\Overrides\HyperfStdoutLogger;
use Hyperf\Extend\Pool\PoolFactory as HyperfPoolFactory;
use Hyperf\Extend\Server\DispatcherFactory as HyperfDispatcherFactory;
use Hyperf\Extend\Server\HttpServerResponseEmitter;
use Hyperf\Extend\Utils\ConfigUtil;
use Hyperf\Extend\Utils\ENV;

class ConfigProvider
{
    public function __invoke(): array
    {
        $redis_configs = [
            // 通用
            'common_redis_config' => ConfigUtil::redisAutoEnv(
                '127.0.0.1',
                6379,
                ''
            ),
        ];
        return [
            'annotations' => [
                'scan' => [
                    'ignore_annotations' => [
                        'OpenApi\Annotations\Get',
                        'OpenApi\Annotations\Post',
                    ],
                    'class_map' => [
                        Connection::class => dirname(__DIR__) . '/class_map/db/Connection.php'
                    ],
                ],
            ],
            'listeners' => [
                ResponseSendFailedOrSlowRequestListener::class,
                OnWorkerExitListener::class,
                ConsoleCommandEventListener::class,
            ],
            'commands' => [
                MysqlCommand::class,
                RedisCommand::class,
            ],
            'dependencies' => [
                ResponseEmitter::class => HttpServerResponseEmitter::class,
                DispatcherFactory::class => HyperfDispatcherFactory::class,
                FormatterInterface::class => ExceptionFormatter::class,
                MigrateCommand::class => HyperfMigrateCommand::class,
                RollbackCommand::class => HyperfRollbackCommand::class,
                DeleteCacheListener::class => HyperfDeleteCacheListener::class,
                PoolFactory::class => HyperfPoolFactory::class,
                StdoutLoggerInterface::class => HyperfStdoutLogger::class,
                // GRPC 微服务熔断
                IGrpcProjectCircuitBreaker::class => GrpcProjectCircuitBreaker::class,
                RedisProxy::class => HyperfRedisProxy::class,
                // 在数据库出错时记录更多信息
                ConnectionFactory::class => ConnectionFactory::class,
            ],
            'middlewares' => [
                'http' => [
                ],
            ],
            'server' => [
                'callbacks' => [
                    Event::ON_WORKER_EXIT => [WorkerExitCallback::class, 'onWorkerExit'],
                    Event::ON_WORKER_STOP => [WorkerStopCallback::class, 'onWorkerStop'],
                ],
            ],
            'i18n' => [
                'paths' => [
                    dirname(__DIR__) . '/config/i18n/',
                ],
            ],
            'simple_configs' => require(dirname(__DIR__) . '/config/simple_configs.php'),
        ];
    }
}
