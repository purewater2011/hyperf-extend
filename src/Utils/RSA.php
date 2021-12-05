<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf server projects.
 */
namespace Hyperf\Extend\Utils;

class RSA
{
    const DEFAULT_PRIVATE_KEY = '-----BEGIN ENCRYPTED PRIVATE KEY-----
todo
-----END ENCRYPTED PRIVATE KEY-----';

    const DEFAULT_PUBLIC_KEY = '-----BEGIN PUBLIC KEY-----
todo
-----END PUBLIC KEY-----';

    const BASE64_CHARS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/';

    private static $default_private_key;

    private static $default_public_key;

    public static function encryptWithPrivateKey($str, $private_key = null)
    {
        self::setupDefaultKey();
        $result = '';
        $str_length = strlen($str);
        $maxlength = 117;
        for ($i = 0; $i < $str_length; $i += $maxlength) {
            $input = substr($str, $i, $maxlength);
            openssl_private_encrypt($input, $encrypted, $private_key ?: self::$default_private_key);
            $result .= $encrypted;
        }
        return $result;
    }

    public static function decryptWithPrivateKey($str, $private_key = null)
    {
        self::setupDefaultKey();
        $result = '';
        $str_length = strlen($str);
        $maxlength = 128;
        for ($i = 0; $i < $str_length; $i += $maxlength) {
            $input = substr($str, $i, $maxlength);
            openssl_private_decrypt($input, $decrypted, $private_key ?: self::$default_private_key);
            $result .= $decrypted;
        }
        return $result;
    }

    public static function decryptWithPublicKey($str, $public_key = null)
    {
        self::setupDefaultKey();
        $result = '';
        $str_length = strlen($str);
        $maxlength = 128;
        for ($i = 0; $i < $str_length; $i += $maxlength) {
            $input = substr($str, $i, $maxlength);
            openssl_public_decrypt($input, $decrypted, $public_key ?: self::$default_public_key);
            $result .= $decrypted;
        }
        return $result;
    }

    public static function encryptWithPublicKey($str, $public_key = null)
    {
        self::setupDefaultKey();
        $result = '';
        $str_length = strlen($str);
        $maxlength = 117;
        for ($i = 0; $i < $str_length; $i += $maxlength) {
            $input = substr($str, $i, $maxlength);
            openssl_public_encrypt($input, $encrypted, $public_key ?: self::$default_public_key);
            $result .= $encrypted;
        }
        return $result;
    }

    public static function doBase64Offset($string, $offset)
    {
        $new_string = '';
        $strlen = strlen($string);
        for ($i = 0; $i < $strlen; ++$i) {
            $index = strpos(self::BASE64_CHARS, $string[$i]);
            if ($index === false) {
                $new_string .= $string[$i];
                continue;
            }
            $index = $index + $offset;
            if ($index >= 64) {
                $index = $index % 64;
            }
            if ($index < 0) {
                $index = $index + 64;
            }
            $base64_chars = self::BASE64_CHARS;
            $new_string .= $base64_chars[$index];
        }
        return $new_string;
    }

    public static function createKey()
    {
        $r = openssl_pkey_new();
        openssl_pkey_export($r, $private_key);
        $public_key = openssl_pkey_get_details($r)['key'];
        echo "private:\n", base64_encode($private_key), "\n\n";
        echo "public:\n", base64_encode($public_key), "\n\n";
    }

    private static function setupDefaultKey()
    {
        if (!self::$default_private_key) {
            self::$default_private_key = openssl_pkey_get_private(self::DEFAULT_PRIVATE_KEY, 'Hyperf');
        }
        if (!self::$default_public_key) {
            self::$default_public_key = openssl_pkey_get_public(self::DEFAULT_PUBLIC_KEY);
        }
    }
}
