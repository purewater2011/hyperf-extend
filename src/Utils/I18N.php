<?php

declare(strict_types=1);
/**
 * This file is part of hyperf server projects.
 */
namespace Hyperf\Extend\Utils;

use Hyperf\Contract\ConfigInterface;
use Hyperf\Utils\ApplicationContext;

class I18N
{
    private static $language_configs = [];

    public static function t($section, $key, $language = null)
    {
        !$language && $language = 'zh-cn';
        $ini = static::loadLanguageConfig($language);

        if (empty($ini) || empty($ini[$section][$key])) {
            return $key;
        }
        return is_array($ini[$section][$key]) ? join("\n", $ini[$section][$key]) : $ini[$section][$key];
    }

    public static function f($section, $key)
    {
        $params = array_slice(func_get_args(), 2);
        $format = self::t($section, $key);

        return preg_replace_callback('/{([0-9]+)}/', function ($matches) use ($params) {
            return isset($params[$matches[1]]) ? $params[$matches[1]] : $matches[0];
        }, $format);
    }

    public static function fl($section, $key, $language)
    {
        $params = array_slice(func_get_args(), 3);
        $format = self::t($section, $key, $language);

        return preg_replace_callback('/{([0-9]+)}/', function ($matches) use ($params) {
            return isset($params[$matches[1]]) ? $params[$matches[1]] : $matches[0];
        }, $format);
    }

    /**
     * @param string $language
     * @return array
     */
    private static function loadLanguageConfig($language)
    {
        if (empty(self::$language_configs[$language])) {
            $config = ApplicationContext::getContainer()->get(ConfigInterface::class);
            $i18n_paths = $config->get('i18n.paths', []);
            $language_configs = [];
            foreach ($i18n_paths as $i18n_path) { // 将获取到的所有翻译文案合并
                $file_path = $i18n_path . $language . '.ini';
                if (!file_exists($file_path)) {
                    LogUtil::stdout()->error('no translation file at ' . $file_path);
                    continue;
                }
                $translation = parse_ini_file($file_path, true);
                if ($translation === false) {
                    LogUtil::logger('i18n')->error('failed to parse i18n ini file ' . $file_path);
                    continue;
                }
                if (empty($language_configs)) {
                    $language_configs = $translation;
                } else {
                    $language_configs = array_merge_recursive($language_configs, $translation);
                }
            }
            self::$language_configs[$language] = $language_configs;
        }

        return self::$language_configs[$language];
    }
}
