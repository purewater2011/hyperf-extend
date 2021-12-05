<?php

declare(strict_types=1);
/**
 * This file is part of hyperf server projects.
 */
namespace Hyperf\Extend\Utils;

use Elasticsearch\Client;
use Elasticsearch\ClientBuilder;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Guzzle\RingPHP\PoolHandler;
use Hyperf\Utils\ApplicationContext;
use Swoole\Coroutine;

class Elasticsearch
{
    public static function get($name = 'default'): Client
    {
        $container = ApplicationContext::getContainer();
        $config = $container->get(ConfigInterface::class)->get("elasticsearch.{$name}");
        if (!$config) {
            return null;
        }
        $builder = ClientBuilder::create();
        if (Coroutine::getCid() > 0) {
            $option = $config['pool'] ?? ['max_connections' => 20];
            $handler = make(PoolHandler::class, [
                'option' => $option,
            ]);
            $builder->setHandler($handler);
        }
        return $builder->setHosts($config['hosts'])->build();
    }
}
