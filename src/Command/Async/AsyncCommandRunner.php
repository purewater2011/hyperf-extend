<?php

declare(strict_types=1);
/**
 * This file is part of hyperf server projects.
 */
namespace Hyperf\Extend\Command\Async;

use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Utils\ApplicationContext;
use RuntimeException;
use Swoole\Coroutine;
use Swoole\Runtime;

/**
 * 异步执行终端命令.
 */
class AsyncCommandRunner
{
    /**
     * @var callable
     */
    private $progress_callback;

    /**
     * @var callable
     */
    private $complete_callback;

    /**
     * @var string
     */
    private $command;

    /**
     * @var string
     */
    private $temp_filepath;

    /**
     * @var resource
     */
    private $process;

    /**
     * @var array
     */
    private $process_pipes;

    /**
     * @var string
     */
    private $process_stdout;

    /**
     * @var string
     */
    private $process_stderr;

    /**
     * @var int
     */
    private $process_exit_code;

    /**
     * @var bool
     */
    private $is_completed = false;

    /**
     * @var bool
     */
    private $is_running = false;

    /**
     * @var int 命令启动时间
     */
    private $start_timestamp = 0;

    /**
     * @var int 命令执行最长超时时间
     */
    private $timeout = 0;

    public function __construct($command)
    {
        Runtime::enableCoroutine(false);
        $this->command = $command;
        $this->temp_filepath = '/tmp/tasks/' . md5($this->command) . '.sh';
        if (!is_dir(dirname($this->temp_filepath))) {
            @mkdir(dirname($this->temp_filepath));
        }
        file_put_contents($this->temp_filepath, $this->command);
    }

    public function exec()
    {
        $this->is_running = true;
        $descriptors = [
            0 => ['pipe', 'r'],  // stdin
            1 => ['pipe', 'w'],  // stdout
            2 => ['pipe', 'w'],  // stderr
        ];
        $this->process = proc_open('sh ' . $this->temp_filepath, $descriptors, $this->process_pipes);
        if (!is_resource($this->process)) {
            throw new RuntimeException('failed to exec ' . $this->command);
        }
        stream_set_blocking($this->process_pipes[1], false);
        stream_set_blocking($this->process_pipes[2], false);
        Coroutine::create(function () {
            $this->checkProcessStatusTillExit();
        });
    }

    public function wait()
    {
        if (Coroutine::getCid() < 0) {
            throw new RuntimeException('can only wait for process in Coroutine');
        }
        while (true) {
            if ($this->is_completed) {
                break;
            }
            Coroutine::sleep(0.01);
        }
    }

    public function getExitCode()
    {
        return $this->process_exit_code;
    }

    public function getStdout()
    {
        return $this->process_stdout;
    }

    public function getStderr()
    {
        return $this->process_stderr;
    }

    public function isCompleted()
    {
        return $this->is_completed;
    }

    public function isRunning()
    {
        return $this->is_running;
    }

    public function setTimeout(int $timeout)
    {
        $this->timeout = $timeout;
    }

    public function setCompleteCallback($complete_callback)
    {
        $this->complete_callback = $complete_callback;
    }

    public function setProgressCallback($progress_callback)
    {
        $this->progress_callback = $progress_callback;
    }

    public function throwExceptionWhenError()
    {
        if ($this->getExitCode() !== 0 && $this->getExitCode() !== -1) {
            throw new RuntimeException(
                "====== STDOUT ======\n" .
                $this->getStdout() .
                "\n====== STDERR ======\n" .
                $this->getStderr()
            );
        }
    }

    private function checkProcessStatusTillExit()
    {
        /** @var StdoutLoggerInterface $logger */
        $logger = ApplicationContext::getContainer()->get(StdoutLoggerInterface::class);
        $this->start_timestamp = time();
        while (true) {
            $read = [$this->process_pipes[1], $this->process_pipes[2]];
            $other = [];
            $count = stream_select($read, $other, $other, 0, 0);
            if ($count != 0) {
                $this->process_stdout .= stream_get_contents($this->process_pipes[1]);
                $this->process_stderr .= stream_get_contents($this->process_pipes[2]);
                if (!empty($this->progress_callback)) {
                    call_user_func($this->progress_callback, $this);
                }
            }
            $status = proc_get_status($this->process);
            if (!$status['running']) {
                $this->process_exit_code = $status['exitcode'];
                $this->process_stdout .= stream_get_contents($this->process_pipes[1]);
                $this->process_stderr .= stream_get_contents($this->process_pipes[2]);
                fclose($this->process_pipes[0]);
                fclose($this->process_pipes[1]);
                fclose($this->process_pipes[2]);
                proc_close($this->process);
                $this->is_completed = true;
                $this->is_running = false;
                unlink($this->temp_filepath);
                if ($this->complete_callback) {
                    call_user_func($this->complete_callback, $this);
                }
                break;
            }
            $now = time();
            $seconds = $now - $this->start_timestamp;
            if ($this->timeout > 0 && $seconds > $this->timeout) {
                $logger->error(
                    "command in running status after {$seconds} seconds, about to kill, process status is:\n" .
                    json_encode($status) . "\n" .
                    'command is ' . $this->command
                );
                proc_terminate($this->process);
            } elseif ($seconds > 3600 && ($now % 300 === 0)) {
                // 进程运行时间过长，打印日志以便排查问题
                $logger->warning(
                    "command in running status after {$seconds} seconds, process status is:\n" .
                    json_encode($status) . "\n" .
                    'command is ' . $this->command
                );
            }
            Coroutine::sleep(0.01);
        }
    }
}
