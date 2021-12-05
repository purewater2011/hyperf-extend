<?php

declare(strict_types=1);
/**
 * This file is part of hyperf server projects.
 */
namespace Hyperf\Extend\Overrides;

use Hyperf\Contract\ConfigInterface;
use Hyperf\Database\ConnectionInterface;
use Hyperf\DbConnection\ConnectionResolver;
use Hyperf\Utils\ApplicationContext;
use Hyperf\Utils\Context;
use Hyperf\Utils\Coroutine;
use Hyperf\Utils\Str;
use Hyperf\Extend\Utils\Util;

class HyperfConnectionResolver extends ConnectionResolver
{
    /**
     * 重写父类的获取连接函数，以便能够实现提前归还数据库连接回连接池.
     * @param null|mixed $name
     */
    private function __connection($name = null)
    {
        if (is_null($name)) {
            $name = $this->getDefaultConnection();
        }

        $connection = null;
        $id = $this->getContextKey($name);
        if (Context::has($id)) {
            $connection = Context::get($id);
        }

        if (!$connection instanceof ConnectionInterface) {
            $pool = $this->factory->getPool($name);
            $connection = $pool->get();
            try {
                // PDO is initialized as an anonymous function, so there is no IO exception,
                // but if other exceptions are thrown, the connection will not return to the connection pool properly.
                $connection = $connection->getConnection();
                Context::set($id, $connection);
            } finally {
                if (Coroutine::inCoroutine()) {
                    defer(function () use ($connection, $id) {
                        // 这个 if 判断函数为改动之处
                        if (Context::has($id)) {
                            Context::set($id, null);
                            $connection->release();
                        }
                    });
                }
            }
        }

        return $connection;
    }

    public function connection($name = null)
    {
        return $this->__connection($name);
    }

    /**
     * 主动释放当前协程环境下的数据库连接.
     */
    public function releaseAllConnections()
    {
        if (!Coroutine::inCoroutine()) {
            return;
        }
        foreach (\Swoole\Coroutine::getContext() as $k => $v) {
            if (!Str::startsWith($k, 'database.connection.')) {
                continue;
            }
            if (!empty($v)) {
                Context::set($k, null);
                $v->release();
            }
        }
    }

    private function getContextKey($name): string
    {
        return sprintf('database.connection.%s', $name);
    }
}
