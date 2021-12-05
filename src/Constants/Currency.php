<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf server projects.
 */
namespace Hyperf\Extend\Constants;

class Currency
{
    const USD = 1; //美元

    const IDR = 2; //印尼卢比

    const VND = 3; //越南盾

    const CNY = 4; //人民币

    const CODES = [
        self::USD => 'USD',
        self::IDR => 'IDR',
        self::VND => 'VND',
        self::CNY => 'CNY',
    ];

    const CODE_IDS = [
        'USD' => self::USD,
        'IDR' => self::IDR,
        'VND' => self::VND,
        'CNY' => self::CNY,
    ];

    const NAMES = [
        self::USD => '美元',
        self::IDR => '印尼卢比',
        self::VND => '越南盾',
        self::CNY => '人民币',
    ];
}
