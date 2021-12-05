<?php

declare(strict_types=1);

/**
 * This file is part of hyperf server projects.
 */
use Hyperf\Extend\Utils\ENV;

//所有项目定义的key需要保证唯一,acm可以进行动态更新数据

if (ENV::isDev() || ENV::isTest()) {
    return [
    ];
}

return [
];
