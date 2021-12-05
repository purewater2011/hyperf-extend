<?php

declare(strict_types=1);
/**
 * This file is part of hyperf server projects.
 */
namespace Hyperf\Extend\Test\Mocks;

use ClickHouseDB\Client;
use ClickHouseDB\Statement;
use ClickHouseDB\Transport\CurlerRequest;
use Hyperf\Di\Container;
use Hyperf\Utils\ApplicationContext;
use Mockery;

/**
 * ClickHouseClient的mock.
 */
trait ClickHouseClientMock
{
    private $click_house_client_mock;

    protected function initClickHouseClientMock()
    {
        $this->clickhouse_client_mock = Mockery::mock(Client::class);
        $this->clickhouse_client_mock->shouldReceive('select')->andReturn(new Statement(new CurlerRequest()))->byDefault();
        $this->clickhouse_client_mock->shouldReceive('database')->andReturn($this->clickhouse_client_mock)->byDefault();
        $this->clickhouse_client_mock->shouldReceive('setTimeout')->andReturn(null)->byDefault();
        $this->clickhouse_client_mock->shouldReceive('setConnectTimeOut')->andReturn(null)->byDefault();
        /** @var Container $container */
        $container = ApplicationContext::getContainer();
        $container->define(Client::class, function () {
            return $this->clickhouse_client_mock;
        });
    }
}
