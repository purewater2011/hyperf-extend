<?php

declare(strict_types=1);
/**
 * This file is part of hyperf server projects.
 */
namespace Hyperf\Extend\Utils;

/**
 * 对 Carbon::now 进行 mock 支持
 */
class Carbon extends \Carbon\Carbon
{
    public function __construct($time = null, $tz = null)
    {
        if (empty($time) || $time === 'now') {
            $time = PHPFunctions::time();
            parent::__construct($time, $tz);
            // 传入 unix timestamp 之后，Carbon 底层会强制被采用 UTC 时区，这里做修正
            $this->setTimezone(static::safeCreateDateTimeZone($tz));
        } else {
            parent::__construct($time, $tz);
        }
    }
}
