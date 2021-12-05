<?php

declare(strict_types=1);
/**
 * This file is part of hyperf server projects.
 */
namespace Hyperf\Extend;

class Constant
{
    /**
     * 用来执行邮箱地址格式验证的正则表达式.
     */
    const REG_EMAIL = '/^[a-zA-Z0-9!#$%&\'*+\\/=?^_`{|}~-]+(?:\.[a-zA-Z0-9!#$%&\'*+\\/=?^_`{|}~-]+)*@(?:[a-zA-Z0-9](?:[a-zA-Z0-9-]*[a-zA-Z0-9])?\.)+[a-zA-Z0-9](?:[a-zA-Z0-9-]*[a-zA-Z0-9])?$/';

    /**
     * 用来获取文件后缀的正则表达式.
     */
    const REG_FILE_SUFFIX = '/\\.([a-zA-Z0-9]+)$/';

    /**
     * 服务器故障.
     */
    const ERROR_SERVER = -100;

    /**
     * 访问了登录才能使用的接口.
     */
    const ERROR_LOGIN_TO_CONTINUE = -1000;

    /**
     * token过期
     */
    const ERROR_TOKEN_EXPIRE = -1001;
}
