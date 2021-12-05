<?php

declare(strict_types=1);
/**
 * This file is part of hyperf server projects.
 */
namespace Hyperf\Extend\Utils\Audio;

use Hyperf\Extend\Utils\Audio\AudioVolumeInfo;
use Hyperf\Extend\Utils\File;

class Audio
{
    private static $ffmpeg_path;

    private static $sox_path;

    /**
     * 检测一个音频文件的音量信息
     * 注意：本函数使用 exec 执行，协程并发环境下可能出现问题.
     * @param string $file_path 进行检测的音频文件路径
     * @param string $duration 进行检测的音频的长度
     */
    public static function detectVolumeInfo(string $file_path, $duration = null): AudioVolumeInfo
    {
        self::init();
        $command = self::$ffmpeg_path;
        if (!empty($duration)) {
            $command .= " -t '{$duration}'";
        }
        $command .= " -i '{$file_path}' -filter_complex volumedetect -c:v copy -f null /dev/null 2>&1";
        $output = self::exec($command);
        $info = new AudioVolumeInfo();
        foreach ($output as $line) {
            if (preg_match('#mean_volume: ([^ ]+) dB#', $line, $matches)) {
                $info->mean_volume = floatval($matches[1]);
            } elseif (preg_match('#max_volume: ([^ ]+) dB#', $line, $matches)) {
                $info->max_volume = floatval($matches[1]);
            } elseif (preg_match('#Duration: ([0-9]{2}):([0-9]{2}):([0-9]{2}\\.[0-9]+)#', $line, $matches)) {
                $info->duration = intval($matches[1]) * 3600 + intval($matches[2]) * 60 + floatval($matches[3]);
            }
        }
        return $info;
    }

    public static function convert(
        string $from_file_path,
        string $to_file_path,
        ?string $offset = null,
        ?string $duration = null,
        ?string $codec = null,
        ?int $sample_rate = null
    ) {
        self::init();
        $command = self::$ffmpeg_path . " -y -i '{$from_file_path}' -vn";
        if (!empty($offset)) {
            $command .= " -ss '{$offset}'";
        }
        if (!empty($duration)) {
            $command .= " -t '{$duration}'";
        }
        if (!empty($codec)) {
            $command .= " -acodec {$codec}";
        }
        if (!empty($sample_rate)) {
            $command .= " -ar {$sample_rate}";
        }
        $command .= " '{$to_file_path}' 2>&1";
        self::exec($command);
    }

    /**
     * 更改一个音频文件的音量大小
     * 注意：本函数使用 exec 执行，协程并发环境下可能出现问题.
     */
    public static function changeVolume(string $from_file_path, string $to_file_path, string $volume)
    {
        self::init();
        $command = self::$ffmpeg_path . " -i '{$from_file_path}' -filter:a 'volume={$volume}' '{$to_file_path}' 2>&1";
        self::exec($command);
    }

    /**
     * 把一个音频文件的音量大小修正至最大音量为近似 0dB.
     */
    public static function changeVolumeToMaxZeroDb(string $from_file_path, string $to_file_path)
    {
        $info = self::detectVolumeInfo($from_file_path);
        if ($info->max_volume < -30) {
            // 把这个文件认为是一个没有前景声音的文件，不做修正
            copy($from_file_path, $to_file_path);
        } elseif ($info->max_volume < 0) {
            self::changeVolume($from_file_path, $to_file_path, (-$info->max_volume . 'dB'));
        }
    }

    /**
     * 检测一个音频文件的头部有多长时间可判定为噪声
     * 噪声段的判定标准为最大识别音量为 -20dB 以下
     * 最长取 1.5s，往下逐步降低时长，步长 0.1 秒
     * 注意：进行噪声识别之前，需要对小音量的音频先进行扩音.
     * @return float 背景噪声的时长，单位秒
     */
    public static function detectNoiseDuration(string $file_path): float
    {
        for ($duration = 1.5; $duration >= 0.1; $duration -= 0.1) {
            $info = self::detectVolumeInfo($file_path, $duration);
            if (empty($info)) {
                break;
            }
            if ($info->duration < $duration) {
                $duration = $info->duration;
            }
            if ($info->max_volume < -20) {
                return $duration;
            }
        }
        return 0;
    }

    /**
     * 使用 sox 软件对音频进行降噪.
     */
    public static function removeNoise(string $from_file_path, string $to_file_path)
    {
        $temp = md5(uniqid());
        $file_name = basename($from_file_path);
        $temp_folder_path = "/tmp/audio/{$temp}/";
        @mkdir($temp_folder_path, 0777, true);
        $noise_prof_file_path = $temp_folder_path . 'noise.prof';
        $noise_wav_file_path = $temp_folder_path . $file_name . '.noise.wav';
        $wav_file_path = $temp_folder_path . $file_name . '.wav';
        $noise_removed_wav_file_path = $temp_folder_path . $file_name . '.no.noise.wav';
        // ● 识别噪声长度
        $noise_duration = self::detectNoiseDuration($from_file_path);
        if ($noise_duration == 0) {
            // 没有识别到噪声，直接做文件复制
            copy($from_file_path, $to_file_path);
            return;
        }
        // ● 把噪声文件转为 wav 格式
        self::convert(
            $from_file_path,
            $noise_wav_file_path,
            null,
            sprintf('00:00:%02.2f', $noise_duration),
            'pcm_s16le'
        );
        // ● 把噪声文件转为 prof
        $command = self::$sox_path . " '{$noise_wav_file_path}' -n noiseprof '{$noise_prof_file_path}'";
        self::exec($command);
        // ● 把音频文件转为 wav 格式
        self::convert($from_file_path, $wav_file_path, null, null, 'pcm_s16le');
        // ● 对 wav 格式的音频进行去噪
        $command = self::$sox_path . " '{$wav_file_path}' '{$noise_removed_wav_file_path}' noisered '{$noise_prof_file_path}' 0.3";
        self::exec($command);
        // ● 转格式回目标文件路径
        self::convert($noise_removed_wav_file_path, $to_file_path);
        self::exec("rm -rf '{$temp_folder_path}'");
    }

    private static function init()
    {
        if (empty(self::$ffmpeg_path)) {
            self::$ffmpeg_path = File::findExecutable('ffmpeg');
            if (empty(self::$ffmpeg_path)) {
                throw new \RuntimeException('cannot find program ffmpeg');
            }
        }
        if (empty(self::$sox_path)) {
            self::$sox_path = File::findExecutable('sox');
            if (empty(self::$sox_path)) {
                throw new \RuntimeException('cannot find program sox');
            }
        }
    }

    private static function exec(string $command): array
    {
        exec($command, $output, $return_var);
        if ($return_var !== 0) {
            $message = 'failed to run command ' . $command;
            $message .= "\n" . join("\n", $output);
            throw new \RuntimeException($message);
        }
        return $output;
    }
}
