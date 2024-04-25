<?php

declare(strict_types=1);
/**
 * This file is part of hyperf server projects.
 */
namespace Hyperf\Extend\Utils;

use Hyperf\Extend\Compatibility\Compatibility;
use Hyperf\Extend\Compatibility\HttpRequest;

/**
 * Client相关的工具类.
 */
class ClientInfoUtil
{
    /**
     * @return string
     */
    public static function getRemoteAddress()
    {
        return self::remoteAddress();
    }

    /**
     * 获取当前客户端请求的IP地址
     * @param string $default
     * @return null|string
     */
    public static function remoteAddress($default = null)
    {
        if (empty(HttpRequest::current())) {
            return $default;
        }
        $remote_address = HttpRequest::getHeader('x-remote-address');
        if (!empty($remote_address)) {
            return $remote_address;
        }
        if (Compatibility::isSwoole()) {
            $real_ip = null;
            if (HttpRequest::current()->header('x-real-ip')) {
                $real_ip = HttpRequest::current()->header('x-real-ip');
            }
            if (empty($real_ip) && HttpRequest::current()->header('x-forwarded-for')) {
                $real_ip = HttpRequest::current()->header('x-forwarded-for');
            }
            if (!empty($real_ip) && strpos($real_ip, ',') !== false) {
                return trim(explode(',', $real_ip)[0]);
            }
            return $real_ip ?: HttpRequest::current()->server('remote_addr', $default);
        }
        return HttpRequest::current()->server('remote_addr', $default);
    }

    public static function getUserAgent($default = null)
    {
        if (empty(HttpRequest::current())) {
            return $default;
        }
        $remote_address = HttpRequest::getHeader('User-Agent');
        if (!empty($remote_address)) {
            return $remote_address;
        }
        return $default;
    }

    /**
     * 对url进行编码转换，返回编码之后的url地址，目前的解析参照firefox对url的解析进行
     * 英文标点中除 ()<>'" 这六个以外，其余都不进行编码原样返回
     * 英文可输入标点按照标准键盘，总共有以下32个
     * ~`!@#$%^&*()_-+=|\/?{}[],.<>;:'".
     * @param string $url
     * @param string $encoding 需要以什么字符编码来encode这个url地址，我们会假设传入的url地址为UTF-8编码来对其进行转换，你可以不使用该参数来避免进行编码转换
     * @return string
     */
    public static function encodeUrlLikeFirefox($url, $encoding = null)
    {
        if ($encoding) {
            $url = iconv('UTF-8', $encoding, $url);
        }
        $not_encode_chars = [
            '0', '1', '2', '3', '4', '5', '6', '7', '8', '9',
            'a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p', 'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z',
            'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z',
            '~', '`', '!', '@', '#', '$', '%', '^', '&', '*', '-', '_', '=', '+', '[', '{', ']', '}', '\\', '|', ';', ':', ',', '.', '/', '?',
        ];
        $encoded_url = '';
        $len = strlen($url);
        for ($i = 0; $i < $len; ++$i) {
            $char = $url[$i];
            if (in_array($char, $not_encode_chars)) {
                $encoded_url .= $char;
            } else {
                $encoded_url .= rawurlencode($char);
            }
        }

        return $encoded_url;
    }

}
