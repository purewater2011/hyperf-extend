<?php

declare(strict_types=1);
/**
 * This file is part of hyperf server projects.
 */
namespace Hyperf\Extend\Utils;

use Hyperf\Extend\Constants\Currency;

class MoneyUtil
{
    /**
     * 美金转印尼币
     * @param float $usd 美金(元)
     * @return float
     */
    public static function convertUSDToIDR($usd)
    {
        return round($usd * 14285.714285714285714);
    }

    /**
     * 印尼币转美金.
     * @param float $idr 印尼币(元)
     * @param int $precision 小数位精度
     * @return float
     */
    public static function convertIDRToUSD($idr, $precision = 2)
    {
        return round($idr / 14285.714285714285714, $precision);
    }

    /**
     * 美金转越南盾.
     * @param float $usd 美金(元)
     * @param int $precision 小数位精度
     * @return float
     */
    public static function convertUSDToVND($usd, $precision = 2)
    {
        return round($usd * 22857.142857142857143, $precision);
    }

    /**
     * 越南盾转美金.
     * @param float $vnd 越南盾(元)
     * @param int $precision 小数位精度
     * @return float
     */
    public static function convertVNDToUSD($vnd, $precision = 2)
    {
        return round($vnd / 22857.142857142857143, $precision);
    }

    /**
     * 将美金转换为对应币种金额.
     * @param float $usd 收入的美金金额
     * @param int $currency 要进行显示的币种
     * @param int $precision 小数位精度
     * @return bool|float|int
     */
    public static function convertFromUSD($usd, $currency, $precision = 2)
    {
        if (empty($currency)) {
            return false;
        }
        if ($usd == 0) {
            return 0;
        }
        if ($currency == Currency::IDR) {
            return round(self::convertUSDToIDR($usd));
        }
        if ($currency == Currency::VND) {
            return round(self::convertUSDToVND($usd), $precision);
        }
        if ($currency == Currency::USD) {
            return round($usd, $precision);
        }
        return false;
    }

    /**
     * 使用千分位格式化显示.
     * @param float $value 金额
     * @param int $currency 金额对应币种
     * @param int $precision 小数位精度
     * @return string
     */
    public static function formatMoney($value, $currency, $precision = 2)
    {
        if ($value == 0) {
            return '0';
        }
        if ($currency == Currency::IDR) {
            return number_format($value, $precision, ',', '.');
        }
        if ($currency == Currency::VND) {
            return number_format($value, $precision, ',', '.');
        }
        return number_format($value, $precision, '.', ',');
    }

    /**
     * 从美金转为对应币种，使用千分位格式化显示.
     * @param float $usd 收入的美金金额
     * @param int $currency 要进行显示的币种
     * @param int $precision 小数位精度
     * @return string
     */
    public static function formatMoneyFromUSD($usd, $currency, $precision = 2)
    {
        $value = self::convertFromUSD($usd, $currency, $precision);
        return self::formatMoney($value, $currency, $precision);
    }

    /**
     * 带单位金额显示.
     * @param float $usd 美金
     * @param int $currency 要进行显示的币种
     * @param int $precision 小数位精度
     * @return string
     */
    public static function formatWithUnitFromUSD($usd, $currency, $precision = 2)
    {
        if ($usd == 0) {
            return ' - ';
        }
        if ($currency == Currency::IDR) {
            return 'RP ' . self::formatMoney(self::convertUSDToIDR($usd), $currency, 0);
        }
        if ($currency == Currency::VND) {
            return 'VND ' . self::formatMoney(self::convertUSDToVND($usd), $currency, $precision);
        }
        if ($currency == Currency::USD) {
            return 'USD ' . self::formatMoney($usd, $currency, $precision);
        }
        return ' - ';
    }

    /**
     * 简化格式化金额显示.
     * @param float $usd 收入的美金金额
     * @param int $currency 要进行显示的币种
     * @return int|string
     */
    public static function formatShortFromUSD($usd, $currency)
    {
        if ($usd == 0) {
            return ' - ';
        }
        if ($currency == Currency::IDR) {
            $value = self::convertUSDToIDR($usd);
            if ($value >= 100000000) {
                return 'RP ' . self::formatMoney($value / 1000000, $currency, 1) . 'Jt';
            }
            if ($value >= 1000000) {
                return 'RP ' . self::formatMoney($value / 1000000, $currency, 2) . 'Jt';
            }
            if ($value >= 100000) {
                return 'RP ' . self::formatMoney($value / 1000, $currency, 1) . 'rb';
            }
            if ($value >= 10000) {
                return 'RP ' . self::formatMoney($value / 1000, $currency, 2) . 'rb';
            }
            return 'RP ' . self::formatMoney($value, $currency, 0);
        }
        if ($currency == Currency::VND) {
            $value = self::convertUSDToVND($usd);
            if ($value >= 100000000) {
                return 'VND ' . self::formatMoney($value / 1000000, $currency, 1) . 'M';
            }
            if ($value >= 1000000) {
                return 'VND ' . self::formatMoney($value / 1000000, $currency, 2) . 'M';
            }
            if ($value >= 100000) {
                return 'VND ' . self::formatMoney($value / 1000, $currency, 1) . 'K';
            }
            if ($value >= 10000) {
                return 'VND ' . self::formatMoney($value / 1000, $currency, 2) . 'K';
            }
            return 'VND ' . self::formatMoney($value, $currency, 0);
        }
        if ($currency == Currency::USD) {
            if ($usd >= 100000) {
                return 'USD ' . self::formatMoney($usd / 1000, $currency, 1) . 'K';
            }
            if ($usd >= 10000) {
                return 'USD ' . self::formatMoney($usd / 1000, $currency, 2) . 'K';
            }
            return 'USD ' . self::formatMoney($usd, $currency, 2);
        }
        return ' - ';
    }
}
