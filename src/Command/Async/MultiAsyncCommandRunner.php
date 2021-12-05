<?php

declare(strict_types=1);
/**
 * This file is part of hyperf server projects.
 */
namespace Hyperf\Extend\Command\Async;

use Swoole\Atomic;

class MultiAsyncCommandRunner
{
    /**
     * @var AsyncCommandRunner[]
     */
    private $command_runners;

    /**
     * @var int 并发执行命令数量
     */
    private $concurrent_size = 10;

    /**
     * 记录目前有多少命令处于执行中.
     * @var Atomic
     */
    private $running_commands_count;

    /**
     * @var callable
     */
    private $progress_callback;

    /**
     * @var callable
     */
    private $complete_callback;

    public function __construct(int $concurrent_size = 10)
    {
        $this->running_commands_count = new Atomic(0);
        $this->concurrent_size = $concurrent_size;
    }

    public function exec($command)
    {
        $command_runner = new AsyncCommandRunner($command);
        $command_runner->setCompleteCallback([$this, 'onCommandComplete']);
        $this->command_runners[] = $command_runner;
        if ($this->running_commands_count->get() < $this->concurrent_size) {
            $this->running_commands_count->add(1);
            $command_runner->exec();
        }
        return $command_runner;
    }

    public function onCommandComplete()
    {
        $this->running_commands_count->sub(1);
        // 检查是否有处于等待执行的任务
        foreach ($this->command_runners as $command_runner) {
            if ($command_runner->isRunning() || $command_runner->isCompleted()) {
                continue;
            }
            if ($this->running_commands_count->get() < $this->concurrent_size) {
                $this->running_commands_count->add(1);
                $command_runner->exec();
            }
        }
        if ($this->isCompleted()) {
            if (!empty($this->complete_callback)) {
                call_user_func($this->complete_callback, $this);
            }
        }
        if (!empty($this->progress_callback)) {
            call_user_func($this->progress_callback, $this);
        }
    }

    public function wait()
    {
        foreach ($this->command_runners as $command_runner) {
            $command_runner->wait();
        }
    }

    public function isCompleted()
    {
        foreach ($this->command_runners as $command_runner) {
            if (!$command_runner->isCompleted()) {
                return false;
            }
        }
        return true;
    }

    public function getCommandsCompletedCount()
    {
        $count = 0;
        foreach ($this->command_runners as $command_runner) {
            if ($command_runner->isCompleted()) {
                ++$count;
            }
        }
        return $count;
    }

    public function hasCommandFailed()
    {
        foreach ($this->command_runners as $command_runner) {
            if ($command_runner->getExitCode() !== 0) {
                return true;
            }
        }
        return false;
    }

    public function getCommandRunners()
    {
        return $this->command_runners;
    }

    public function setCompleteCallback($complete_callback)
    {
        $this->complete_callback = $complete_callback;
    }

    public function setProgressCallback($progress_callback)
    {
        $this->progress_callback = $progress_callback;
    }
}
