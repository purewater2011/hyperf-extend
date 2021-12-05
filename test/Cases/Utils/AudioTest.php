<?php

declare(strict_types=1);
/**
 * This file is part of hyperf server projects.
 */
namespace Test\Cases\Utils;

use Hyperf\Extend\Utils\Audio\Audio;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @coversNothing
 */
class AudioTest extends TestCase
{
    public function testDetectVolumeInfo()
    {
        $info = Audio::detectVolumeInfo(__DIR__ . '/AudioTest/AudioTest.mp3');
        $this->assertNotEmpty($info);
        $this->assertEquals(-34, $info->mean_volume);
        $this->assertEquals(-14.2, $info->max_volume);
        $this->assertEquals(4.86, $info->duration);

        $info = Audio::detectVolumeInfo(__DIR__ . '/AudioTest/AudioTest.mp3', '00:00.1');
        $this->assertNotEmpty($info);
        $this->assertEquals(-91, $info->mean_volume);
        $this->assertEquals(-91, $info->max_volume);
        $this->assertEquals(4.86, $info->duration);
    }

    public function testChangeVolumeToMaxZeroDb()
    {
        $from_file_path = __DIR__ . '/AudioTest/AudioTest.mp3';
        $to_file_path = __DIR__ . '/AudioTest/AudioTest2.mp3';
        Audio::changeVolumeToMaxZeroDb($from_file_path, $to_file_path);
        $info = Audio::detectVolumeInfo($to_file_path);
        $this->assertNotEmpty($info);
        $this->assertGreaterThan(-1, $info->max_volume);
        unlink($to_file_path);
    }

    public function testDetectNoiseDuration()
    {
        $from_file_path = __DIR__ . '/AudioTest/AudioTest-louder.mp3';
        $noise_duration = Audio::detectNoiseDuration($from_file_path);
        $this->assertEquals(1.3, $noise_duration);
    }

    public function testConvert()
    {
        $from_file_path = __DIR__ . '/AudioTest/AudioTest.mp3';
        $to_file_path = __DIR__ . '/AudioTest/AudioTest.wav';
        Audio::convert($from_file_path, $to_file_path);
        $info = Audio::detectVolumeInfo($to_file_path);
        $this->assertEquals(-34, $info->mean_volume);
        $this->assertEquals(-14.2, $info->max_volume);
        $this->assertEquals(4.86, $info->duration);
        unlink($to_file_path);
    }

    public function testRemoveNoise()
    {
        $from_file_path = __DIR__ . '/AudioTest/AudioTest-louder.mp3';
        $to_file_path = __DIR__ . '/AudioTest/AudioTest-louder-noise-removed.mp3';
        Audio::removeNoise($from_file_path, $to_file_path);
        $info = Audio::detectVolumeInfo($to_file_path);
        $this->assertGreaterThan(-21.5, $info->mean_volume);
        $this->assertLessThan(-21, $info->mean_volume);
        $this->assertEquals(4.9, $info->duration);
        unlink($to_file_path);
    }
}
