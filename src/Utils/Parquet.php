<?php

declare(strict_types=1);
/**
 * This file is part of hyperf server projects.
 */
namespace Hyperf\Extend\Utils;

class Parquet
{
    /**
     * 提取一个 parquet 文件的 schema 信息.
     */
    public static function schema(string $filepath): array
    {
        $parquet_tools = File::findExecutable('parquet-tools');
        if (empty($parquet_tools)) {
            $message = "parquet-tools not installed, you can use the following command to install:\n";
            if (PHP_OS === 'Darwin') {
                $message .= 'brew install parquet-tools';
            } else {
                $message .= "yum install -y parquet-tools\n";
                $message .= '# centos 版 parquet-tools 是自行打包在私有软件仓库中的';
            }
            throw new \RuntimeException($message);
        }
        $command = $parquet_tools . " schema '{$filepath}'";
        $lines = Exec::execAndCheckReturnVar($command);
        $output = join("\n", $lines);
        if ($lines[0] !== 'message schema {' || $lines[count($lines) - 2] !== '}') {
            throw new \RuntimeException('failed to get schema from ' . $filepath . "\n" . $output);
        }
        $columns = [];
        foreach ($lines as $i => $line) {
            if (preg_match('/(boolean|int32|int64|int96|binary|float|double) ([^ ;]+)/', $line, $matches)) {
                $columns[$matches[2]] = $matches[1];
            }
        }
        return $columns;
    }
}
