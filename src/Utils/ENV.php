<?php

declare(strict_types=1);
/**
 * This file is part of hyperf server projects.
 */
namespace Hyperf\Extend\Utils;

use Hyperf\Contract\ConfigInterface;
use Hyperf\Utils\ApplicationContext;
use Hyperf\Utils\Str;
use Hyperf\Extend\Command\MultiActionBaseCommand;
use Hyperf\Extend\Compatibility\Compatibility;
use Hyperf\Extend\Constants\Ucloud;
use Hyperf\Extend\Listener\ConsoleCommandEventListener;
use Symfony\Component\Console\Command\Command;

class ENV
{
    public static function isDev(): bool
    {
        return PHP_OS === 'Darwin' || PHP_OS === 'WINNT' || env('APP_ENV') === 'dev';
    }

    public static function isTest(): bool
    {
        return get_cfg_var('datacenter') === 'test'
            || env('APP_ENV', 'dev') === 'test';
    }

    public static function isPre(): bool
    {
        return get_cfg_var('datacenter') === 'pre'
          || env('DEPLOY') === 'pre';
    }

    public static function isRunningUnitTests(): bool
    {
        return defined('PHPUNIT_COMPOSER_INSTALL');
    }

    /**
     * 判断当前执行环境是否运行在命令行执行命令的模式下
     * 排除 hyperf start 服务启动命令.
     */
    public static function isRunningCliCommand(): bool
    {
        if (!Compatibility::isHyperf()) {
            return !Compatibility::isSwoole() && php_sapi_name() === 'cli';
        }
        $running_command = self::getRunningCommand();
        return !empty($running_command) && ($running_command instanceof MultiActionBaseCommand);
    }

    /**
     * 获取到当前正在执行的命令行实例.
     */
    public static function getRunningCommand(): ?Command
    {
        return ConsoleCommandEventListener::getRunningCommand();
    }

}
