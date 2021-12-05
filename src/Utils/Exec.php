<?php

declare(strict_types=1);
/**
 * This file is part of hyperf server projects.
 */
namespace Hyperf\Extend\Utils;

class Exec
{
    /**
     * 执行一个本地命令并检查执行结果是否成功
     * @param string $command 要执行的命令
     * @return string[] 命令输出日志
     */
    public static function execAndCheckReturnVar(string $command): array
    {
        $command = "set -e;\n{$command}\nexit 0\n";
        $temp_filepath = '/tmp/tasks/' . md5($command) . '.sh';
        if (!file_put_contents($temp_filepath, $command)) {
            @mkdir(dirname($temp_filepath));
            if (!file_put_contents($temp_filepath, $command)) {
                throw new \RuntimeException('failed to save command to temp file ' . $temp_filepath);
            }
        }
        \Swoole\Runtime::enableCoroutine(false);
        exec('/bin/bash ' . $temp_filepath, $output, $return_var);
        @unlink($temp_filepath);
        if ($return_var !== 0) {
            LogUtil::logger('exec')->error(join("\n", $output));
            LogUtil::stdout()->error(join("\n", $output));
            $exception_message = 'failed to exec command ' . $command;
            $exception_message .= "\nerror code {$return_var}\n";
            throw new \RuntimeException($exception_message);
        }
        return $output;
    }
}
