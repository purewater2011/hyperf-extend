<?php

declare(strict_types=1);
/**
 * This file is part of hyperf server projects.
 */
use Hyperf\Di\Container;
use Hyperf\Di\Definition\DefinitionSourceFactory;
use Hyperf\Utils\ApplicationContext;
use Hyperf\Extend\ConfigProvider;

$container = new Container((new DefinitionSourceFactory(true))());
foreach ((new ConfigProvider())()['dependencies'] as $name => $value) {
    // 修复 ConfigProvider 中配置的 dependencies 在单元测试中无法生效的问题
    $container->getDefinitionSource()->addDefinition($name, $value);
}

if (!$container instanceof \Psr\Container\ContainerInterface) {
    throw new RuntimeException('The dependency injection container is invalid.');
}
return ApplicationContext::setContainer($container);
