<?php

declare(strict_types=1);
/**
 * This file is part of hyperf server projects.
 */
namespace Test\Config;

use Hyperf\Utils\Composer;

/**
 * 继承 hyperf 默认的 ProviderConfig 目的是为了能够把项目本身的 Hyperf\Extend\ConfigProvider 配置也被读取
 * hyperf 框架默认只能读取到依赖库的 composer extra 配置项.
 */
class ProviderConfig extends \Hyperf\Config\ProviderConfig
{
    /**
     * @var array
     */
    private static $providerConfigs = [];

    public static function load(): array
    {
        if (!static::$providerConfigs) {
            $providers = Composer::getMergedExtra('hyperf')['config'] ?? [];
            // 这行代码是继承 ProviderConfig, ConfigFactory 的唯一目的
            $providers[] = \Hyperf\Extend\ConfigProvider::class;
            static::$providerConfigs = static::loadProviders($providers);
        }
        return static::$providerConfigs;
    }
}
