<?php

declare(strict_types=1);
/**
 * This file is part of hyperf server projects.
 */
namespace Hyperf\Extend\Utils;

use DirectoryIterator;
use Hyperf\Utils\Str;
use RuntimeException;

class File
{
    public const IMAGE_SUFFIXES = [
        'jpg', 'jpeg', 'png', 'gif', 'bmp', 'ico', 'tif',
    ];

    public const COMPRESSED_ARCHIVE_SUFFIXES = [
        'zip', 'tar.gz', 'tar.bz2', 'gz', 'rar', 'tar',
    ];

    /**
     * 从文件名中获取一个文件的后缀
     * @param string $filename
     * @return string
     */
    public static function getSuffixOfFilename($filename)
    {
        if (preg_match('/\\.([\\.a-z0-9A-Z]+)$/', $filename, $matches)) {
            $suffix = $matches[1];
            // 以下代码是为了兼容用户使用 1-1.2.jpg 这种命名方式的情况
            if (in_array($suffix, self::COMPRESSED_ARCHIVE_SUFFIXES)) {
                return $suffix;
            }
            if (preg_match('/\\.([a-z0-9A-Z]+)$/', $suffix, $matches)) {
                return $matches[1];
            }
            return $suffix;
        }
    }

    /**
     * 判断一个文件后缀是不是图片.
     * @param string $suffix
     * @return bool
     */
    public static function isFileSuffixImage($suffix)
    {
        return in_array(strtolower($suffix), self::IMAGE_SUFFIXES);
    }

    public static function sortFileNames($file_names)
    {
        $file_names_sorted = [];
        foreach ($file_names as $file_name) {
            $file_name_for_sort = str_replace(
                ['一', '二', '三', '四', '五', '六', '七', '八', '九', '十'],
                ['1', '2', '3', '4', '5', '6', '7', '8', '9', '10'],
                $file_name
            );
            $num = preg_match('/([0-9]+)/', $file_name_for_sort, $matches) ? intval($matches[1]) : PHP_INT_MAX;
            $file_names_sorted[$file_name] = ['num' => $num, 'name' => $file_name];
        }
        uasort($file_names_sorted, function ($a, $b) {
            if ($a['num'] === PHP_INT_MAX && $b['num'] === PHP_INT_MAX) {
                return strcasecmp($a['name'], $b['name']);
            }
            if ($a['num'] === $b['num']) {
                return strcasecmp($a['name'], $b['name']);
            }
            return $a['num'] - $b['num'];
        });

        return array_keys($file_names_sorted);
    }

    /**
     * 计算一个文件或者目录的字节大小.
     * @param string $file_path 文件或者目录的路径
     * @return int
     */
    public static function calculateFileOrFolderSize($file_path)
    {
        $file_size = 0;
        if (is_file($file_path)) {
            $file_size += filesize($file_path);
        } elseif (is_dir($file_path)) {
            if ($file_path[-1] !== '/') {
                $file_path .= '/';
            }
            $dir = opendir($file_path);
            while ($d = readdir($dir)) {
                if ($d == '.' || $d == '..') {
                    continue;
                }
                $sub_file_path = $file_path . $d;
                $file_size += self::calculateFileOrFolderSize($sub_file_path);
            }
        }

        return $file_size;
    }

    /**
     * @param string $path the folder path
     * @param mixed $extension string or array, if specified, only files with theses extensions will be listed
     * @param int $depth the depth, default -1 means not limit depth of search, 1 means only current and no sub folders
     * @return \SplFileInfo[]
     */
    public static function listAllFilesWithExtensionUnder($path, $extension = null, $depth = -1)
    {
        if (!is_dir($path)) {
            return [];
        }
        if (is_string($extension)) {
            $extensions = [strtolower($extension)];
        } elseif (is_array($extension)) {
            $extensions = [];
            foreach ($extension as $item) {
                $extensions[] = strtolower($item);
            }
        } else {
            $extensions = [];
        }

        $files = [];
        $it = new DirectoryIterator($path);
        while ($it->valid()) {
            if ($it->isDot()) {
            } elseif ($it->isFile()) {
                if (empty($extensions)) {
                    $files[] = $it->getFileInfo();
                } elseif (in_array(strtolower($it->getExtension()), $extensions)) {
                    $files[] = $it->getFileInfo();
                }
            } elseif ($it->isDir()) {
                if ($depth != 0) {
                    $subpath = $it->getPath() . DIRECTORY_SEPARATOR . $it->getFilename();
                    $subfiles = self::listAllFilesWithExtensionUnder($subpath, $extensions, $depth - 1);
                    foreach ($subfiles as $item) {
                        $files[] = $item;
                    }
                }
            }
            $it->next();
        }

        return $files;
    }

    /**
     * @param int $size
     * @return string
     */
    public static function formatFileSize($size)
    {
        if ($size < 1024) {
            return '<1K';
        }
        if ($size < 1024 * 1024) {
            return (round($size * 10 / 1024) / 10) . 'K';
        }
        if ($size < 1024 * 1024 * 1024) {
            return (round($size * 10 / 1024 / 1024) / 10) . 'M';
        }
        return (round($size * 10 / 1024 / 1024 / 1024) / 10) . 'G';
    }

    /**
     * 判断一个目录是否是空目录.
     * @param $dir
     * @return bool
     */
    public static function isDirEmpty($dir)
    {
        if (!is_readable($dir)) {
            throw new RuntimeException('cannot read dir ' . $dir);
        }
        if (!is_dir($dir)) {
            throw new RuntimeException($dir . ' is not dir');
        }
        return count(scandir($dir)) == 2;
    }

    /**
     * 从一批文件路径中找到一个存在的可执行文件.
     * @param string[] $folder_paths
     * @return string
     */
    public static function findExecutableInFolderPaths(string $name, array $folder_paths): ?string
    {
        foreach ($folder_paths as $folder_path) {
            $file_path = $folder_path[strlen($folder_path) - 1] === '/'
                ? $folder_path . $name
                : $folder_path . '/' . $name;
            if (is_executable($file_path)) {
                return $file_path;
            }
        }
        return null;
    }

    /**
     * 在默认的系统目录下找到一个可执行文件.
     * @return string
     */
    public static function findExecutable(string $name): ?string
    {
        return self::findExecutableInFolderPaths($name, [
            '/Applications/MAMP/Library/bin/',
            '/usr/local/bin/',
            '/usr/bin/',
            '/usr/sbin/',
        ]);
    }

    /**
     * 下载一个 URL 地址到本地某个文件.
     */
    public static function download(string $url, string $file_path): bool
    {
        $axel = self::findExecutable('axel');
        if (empty($axel)) {
            if (PHP_OS === 'Darwin') {
                LogUtil::stdout()->error('Please install axel first, you can use command: brew install axel');
            } else {
                LogUtil::stdout()->error('Please install axel first');
            }
        }
        @mkdir(dirname($file_path), 0777, true);
        $command = "rm -f '{$file_path}'; axel -n 10 -o '{$file_path}' '{$url}'";
        $output = [];
        exec($command, $output, $return_var);
        if ($return_var === 0 && filesize($file_path) > 0) {
            return true;
        }
        throw new RuntimeException('failed to download url ' . $url);
    }

    /**
     * 读取一个文件，并对每一行内容执行回调函数.
     */
    public static function foreachLine(string $file_path, callable $callback)
    {
        $is_gz = Str::endsWith($file_path, '.gz');
        $handle = $is_gz ? gzopen($file_path, 'r') : fopen($file_path, 'r');
        while (true) {
            $line = $is_gz ? gzgets($handle) : fgets($handle);
            if ($line === false) {
                break;
            }
            if (empty($line)) {
                continue;
            }
            $callback($line);
        }
        $is_gz ? gzclose($handle) : fclose($handle);
    }

    /**
     * 把图片压制为 webp 格式.
     * @param string $from
     * @param string $to
     * @return bool
     */
    public static function convertImageToWebp($from, $to)
    {
        $cwebp = '/usr/local/bin/cwebp';
        if (file_exists('/usr/bin/cwebp')) {
            $cwebp = '/usr/bin/cwebp';
        }
        if (self::isFileFormatGif($from)) {
            $cwebp = '/usr/local/bin/gif2webp';
            if (file_exists('/usr/bin/gif2webp')) {
                $cwebp = '/usr/bin/gif2webp';
            }
        }
        $command = "'{$cwebp}' '{$from}' -o '{$to}'";
        exec($command, $output, $result);

        return $result === 0 && is_file($to);
    }

    /**
     * 判断一个文件名是不是 gif 格式.
     * @param $file_name
     * @return bool
     */
    public static function isFileFormatGif($file_name)
    {
        return !empty(@imagecreatefromgif($file_name));
    }

    /**
     * 判断一个文件名是不是 png 格式.
     * @param $file_name
     * @return bool
     */
    public static function isFileFormatPng($file_name)
    {
        return !empty(@imagecreatefrompng($file_name));
    }

    /**
     * 优化 png 文件.
     * @param $png_file_path
     * @return bool
     */
    public static function optimizeImagePNG($png_file_path)
    {
        $pngquant = '';
        if (is_executable('/usr/bin/pngquant')) {
            $pngquant = '/usr/bin/pngquant';
        }
        if (empty($pngquant) && is_executable('/usr/local/pngquant/pngquant')) {
            $pngquant = '/usr/local/pngquant/pngquant';
        }
        if (is_executable($pngquant)) {
            $pngquant_temp_path = str_replace('.png', '', $png_file_path) . '-' . time() . '-pngquant.png';
            $command = "{$pngquant} --quality=65-80 --strip --output '{$pngquant_temp_path}' -- '{$png_file_path}'";
            exec($command, $output, $result);
            if ($result === 0) {
                @copy($pngquant_temp_path, $png_file_path);
                @unlink($pngquant_temp_path);
                return true;
            }
        }

        //可以进一步压缩
        $result = null;
        $optipng = '/usr/bin/optipng';
        if (is_executable('/usr/local/bin/optipng')) {
            $optipng = '/usr/local/bin/optipng';
        }
        $command = "{$optipng} -strip all -quiet -o3 '{$png_file_path}'";
        exec($command, $output, $result);
        if ($result === 0) {
            return true;
        }
        return false;
    }

    /**
     * 优化 jpg 文件.
     * @param string $from
     * @param string $to
     * @return bool
     */
    public static function optimizeImageJPG($from, $to)
    {
        $jpegtran = '/usr/local/bin/jpegtran';
        if (file_exists('/usr/bin/jpegtran')) {
            $jpegtran = '/usr/bin/jpegtran';
        }
        $command = "{$jpegtran} -copy none -optimize -progressive -outfile '{$to}' '{$from}'";
        exec($command, $output, $result);

        return $result === 0 && is_file($to);
    }

    /**
     * 判断一个文件名是不是 jpg 格式.
     * @param $file_name
     * @return bool
     */
    public static function isFileFormatJpg($file_name)
    {
        return !empty(@imagecreatefromjpeg($file_name));
    }
}
