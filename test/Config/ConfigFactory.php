<?php

declare(strict_types=1);
/**
 * This file is part of hyperf server projects.
 */
namespace Test\Config;

use Hyperf\Config\Config;
use Psr\Container\ContainerInterface;
use Symfony\Component\Finder\Finder;

/**
 * 继承 hyperf 默认的 ConfigFactory 目的是为了能够把项目本身的 Hyperf\Extend\ConfigProvider 配置也被读取
 * hyperf 框架默认只能读取到依赖库的 composer extra 配置项.
 */
class ConfigFactory extends \Hyperf\Config\ConfigFactory
{
    public function __invoke(ContainerInterface $container)
    {
        $autoload_config = $this->readPaths([__DIR__ . '/autoload']);
        // 下面这行是继承框架自带类的唯一作用，替换 ProviderConfig 类
        $merged = array_merge_recursive(ProviderConfig::load(), ...$autoload_config);
        return new Config($merged);
    }

    private function readPaths(array $paths)
    {
        $configs = [];
        $finder = new Finder();
        $finder->files()->in($paths)->name('*.php');
        foreach ($finder as $file) {
            $configs[] = [
                $file->getBasename('.php') => require $file->getRealPath(),
            ];
        }
        return $configs;
    }
}
