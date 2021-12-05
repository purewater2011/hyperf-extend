<?php

declare(strict_types=1);
/**
 * This file is part of hyperf server projects.
 */
namespace Hyperf\Extend\Command\Traits;

use Hyperf\Extend\Command\Async\AsyncCommandRunner;

trait RemoteCommandRunner
{
    /**
     * 在指定的服务器上执行命令.
     * @param string $server_ip 服务器的 IP 地址
     * @param string $command 要执行的命令
     * @param string $user 使用哪个账号来登录远端服务器
     * @param int $timeout 命令最大执行时间
     */
    protected function execOnServer(string $server_ip, string $command, string $user = 'root', int $timeout = 0): AsyncCommandRunner
    {
        $command = $this->getCommandToRunOnServer($server_ip, $command, $user);
        $runner = new AsyncCommandRunner($command);
        $runner->exec();
        $runner->wait();
        $runner->setTimeout($timeout);
        return $runner;
    }

    private function getCommandToRunOnServer(string $server_ip, string $command, string $user = 'root')
    {
        return 'ssh -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null '
            . "{$user}@{$server_ip} <<EOF\nset -e;\n{$command}\nexit 0\nEOF";
    }
}
