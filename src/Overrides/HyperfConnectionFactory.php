<?php

declare(strict_types=1);
/**
 * This file is part of hyperf server projects.
 */
namespace Hyperf\Extend\Overrides;

use Hyperf\Database\Connectors\ConnectionFactory;
use Hyperf\Utils\Arr;
use Hyperf\Extend\Utils\LogUtil;
use PDOException;

class HyperfConnectionFactory extends ConnectionFactory
{
    protected function createPdoResolverWithHosts(array $config)
    {
        return function () use ($config) {
            foreach (Arr::shuffle($hosts = $this->parseHosts($config)) as $key => $host) {
                $config['host'] = $host;

                try {
                    return $this->createConnector($config)->connect($config);
                } catch (PDOException $e) {
                    LogUtil::logThrowable($e);
                    continue;
                }
            }

            throw $e;
        };
    }
}
