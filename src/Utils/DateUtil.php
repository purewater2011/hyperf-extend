<?php

declare(strict_types=1);
/**
 * This file is part of hyperf server projects.
 */
namespace Hyperf\Extend\Utils;

use DateTime;
use DateTimeZone;

class DateUtil
{
    /**
     * 获取格式化之后的日期
     * @param int|string $date 可以传入 -1 或者 yesterday 表示昨天
     * @return false|string
     */
    public static function getRealDate($date)
    {
        if ($date === 'today') {
            return PHPFunctions::date('Y-m-d');
        }
        if ($date === 'tomorrow') {
            return PHPFunctions::date('Y-m-d', PHPFunctions::time() + 86400);
        }
        if ($date === 'yesterday') {
            return PHPFunctions::date('Y-m-d', PHPFunctions::time() - 86400);
        }
        if ($date === 'yesterday2') {
            return PHPFunctions::date('Y-m-d', PHPFunctions::time() - 86400 * 2);
        }
        if (is_numeric($date) || is_int($date)) {
            return PHPFunctions::date('Y-m-d', PHPFunctions::time() + 86400 * intval($date));
        }
        return $date;
    }

    /**
     * 从某一天至某一天，每天都执行 callback 程序.
     * @param string $from
     * @param string $to
     * @param callable $callback
     */
    public static function everyday($from, $to, $callback)
    {
        $from = self::getRealDate($from);
        $to = self::getRealDate($to);
        $from_timestamp = strtotime($from);
        $to_timestamp = strtotime($to);
        if ($from_timestamp <= $to_timestamp) {
            for ($time = $from_timestamp; $time <= $to_timestamp; $time += 86400) {
                $callback(date('Y-m-d', $time));
            }
        } else {
            for ($time = $from_timestamp; $time >= $to_timestamp; $time -= 86400) {
                $callback(date('Y-m-d', $time));
            }
        }
    }

    /**
     * 计算某一天的后 N 天的日期
     */
    public static function dateAfter(string $date, int $days): string
    {
        $timestamp = strtotime(self::getRealDate($date));
        return PHPFunctions::date('Y-m-d', $timestamp + 86400 * $days);
    }

    /**
     * 计算某一天的前 N 天的日期
     */
    public static function dateBefore(string $date, int $days): string
    {
        $timestamp = strtotime(self::getRealDate($date));
        return PHPFunctions::date('Y-m-d', $timestamp - 86400 * $days);
    }

    /**
     * 从某一天至某一天，每周都执行 callback 程序.
     * @param string $from
     * @param string $to
     * @param callable $callback
     */
    public static function everyWeek($from, $to, $callback)
    {
        $from = self::getWeekStart(self::getRealDate($from));
        $to = self::getRealDate($to);
        $from_timestamp = strtotime($from);
        $to_timestamp = strtotime($to);
        for ($time = $from_timestamp; $time <= $to_timestamp; $time += 86400 * 7) {
            $callback(date('Y-m-d', $time), date('Y-m-d', $time + 86400 * 6));
        }
    }

    public static function getWeekStart($date)
    {
        $timestamp = strtotime($date);
        $day_of_week = date('N', $timestamp);
        if ($day_of_week === 1) {
            return $date;
        }
        return date('Y-m-d', $timestamp - ($day_of_week - 1) * 86400);
    }

    /**
     * 从某一天至某一天，每个月都执行 callback 程序.
     * @param string $from
     * @param string $to
     * @param callable $callback
     */
    public static function everyMonth($from, $to, $callback)
    {
        $from = self::getRealDate($from);
        $to = self::getRealDate($to);
        $from_timestamp = strtotime(date('Y-m-01', strtotime($from)));
        $to_timestamp = strtotime($to);
        for ($time = $from_timestamp; $time <= $to_timestamp;) {
            $next_month_start = date('Y-m-01', $time + 86400 * 32);
            $callback(date('Y-m-01', $time), date('Y-m-d', strtotime($next_month_start) - 1));
            $time = strtotime($next_month_start);
        }
    }

    /**
     * 在系统的 date 函数基础上增加了时区支持
     * @param null|int $timestamp
     * @param null|int $timezone 整形的时区，北京时间为 8，纽约时区为 -4
     * @return string
     */
    public static function date(string $format, $timestamp = null, $timezone = null)
    {
        $timezone_name = sprintf('%02d00', $timezone ?? 8);
        if ($timezone >= 0) {
            $timezone_name = '+' . $timezone_name;
        }
        $datetime = new DateTime('now', new DateTimeZone($timezone_name));
        $datetime->setTimestamp($timestamp ?? PHPFunctions::time());
        return $datetime->format($format);
    }

    /**
     * 把字符串格式的时间转化为某一个时区下的 unix timestamp.
     * @param string $datetime
     * @param null|int $timezone
     * @return int
     */
    public static function strtotime($datetime, $timezone = null)
    {
        if ($timezone === null) {
            return strtotime($datetime);
        }
        $system_timezone = (new DateTime())->getOffset() / 3600;
        return strtotime($datetime) - ($timezone - $system_timezone) * 3600;
    }

    public static function today($timezone = null)
    {
        return self::date('Y-m-d', null, $timezone);
    }

    public static function yesterday($timezone = null)
    {
        return self::date('Y-m-d', PHPFunctions::time() - 86400, $timezone);
    }

    public static function getWeekDay($i = 0)
    {
        if ($i == 1) {
            return 'monday';
        }
        if ($i == 2) {
            return 'tuesday';
        }
        if ($i == 3) {
            return 'wednesday';
        }
        if ($i == 4) {
            return 'thursday';
        }
        if ($i == 5) {
            return 'friday';
        }
        if ($i == 6) {
            return 'saturday';
        }
        return 'sunday';
    }
}
