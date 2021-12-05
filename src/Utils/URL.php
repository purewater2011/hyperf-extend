<?php

declare(strict_types=1);
/**
 * This file is part of hyperf server projects.
 */
namespace Hyperf\Extend\Utils;

use Hyperf\Utils\Str;

class URL
{
    /**
     * 给一个 URL 地址上追加一批 query 参数.
     */
    public static function appendQueryParams(string $url, array $params): string
    {
        if (!Str::contains($url, '?')) {
            $url .= '?';
        } elseif (!Str::endsWith($url, '&')) {
            $url .= '&';
        }
        return $url . http_build_query($params);
    }
}
