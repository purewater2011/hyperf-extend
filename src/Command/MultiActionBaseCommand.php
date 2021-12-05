<?php

declare(strict_types=1);
/**
 * This file is part of hyperf server projects.
 */

namespace Hyperf\Extend\Command;

use Hyperf\Command\Command as HyperfCommand;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\DbConnection\Db;
use Hyperf\Utils\ApplicationContext;
use Hyperf\Extend\Listener\ConsoleCommandEventListener;
use Hyperf\Extend\Utils\DbUtil;
use Hyperf\Extend\Utils\ExecUtil;
use ReflectionClass;
use ReflectionMethod;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class MultiActionBaseCommand extends HyperfCommand
{
    /**
     * @var StdoutLoggerInterface
     */
    protected $logger;

    public function __construct(string $name = null)
    {
        parent::__construct($name);
        $this->addArgument('action', InputArgument::REQUIRED, 'the method to run in command class');

        $action = $_SERVER['argv'][2] ?? '';
        $actions_available = $this->listActionsAvailable();
        if (in_array($action, $actions_available)) {
            $rc = new ReflectionMethod(static::class, $action . 'Action');
            foreach ($rc->getParameters() as $param) {
                $this->addOption(
                    $param->getName(),
                    null,
                    $param->isOptional() ? InputOption::VALUE_OPTIONAL : InputOption::VALUE_REQUIRED,
                    '',
                    $param->isDefaultValueAvailable() ? $param->getDefaultValue() : null
                );
            }
        }
        $this->logger = ApplicationContext::getContainer()->get(StdoutLoggerInterface::class);
    }

    public function handle()
    {
        // 对 Event 机制有时不生效的情况进行兼容
        ConsoleCommandEventListener::setRunningCommand($this);
        $action = $this->input->getArgument('action');
        $rc = new ReflectionMethod(static::class, $action . 'Action');
        $function_arguments = [];
        foreach ($rc->getParameters() as $param) {
            $function_arguments[] = $this->input->getOption($param->getName());
        }
        try {
            call_user_func([$this, $action . 'Action'], ...$function_arguments);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            $this->output->writeln($e->getTraceAsString());
            throw new \RuntimeException($e->getMessage(), $e->getCode());
        }
    }

    /**
     * Write a string as standard output.
     *
     * @param mixed $string
     * @param null|mixed $style
     * @param null|mixed $verbosity
     */
    public function line($string, $style = null, $verbosity = null)
    {
        if (empty($this->output)) {
            if (!is_null($style) && method_exists($this->logger, $style)) {
                $this->logger->{$style}(date('Y-m-d H:i:s') . ' ' . $string);
            } else {
                $this->logger->info(date('Y-m-d H:i:s') . ' ' . $string);
            }
        } else {
            parent::line(
                date('Y-m-d H:i:s') . ' ' . $string,
                $style,
                $verbosity
            );
        }
    }

    protected function debug($message)
    {
        $this->logger->debug(date('Y-m-d H:i:s') . ' ' . $message);
    }

    protected function notice($message)
    {
        $this->logger->notice(date('Y-m-d H:i:s') . ' ' . $message);
    }

    // 暂时关闭enable-event-dispatcher, 会引起exit_code退出码异常
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $input->setOption('enable-event-dispatcher', true);
        return parent::execute($input, $output);
    }

    /**
     * 执行一个本地命令并检查执行结果是否成功
     * @param string $command 要执行的命令
     * @return string[] 命令输出日志
     */
    protected function execAndCheckReturnVar(string $command): array
    {
        return ExecUtil::execAndCheckReturnVar($command);
    }

    /**
     * 快捷的批量插入数据库函数.
     */
    protected function batchInsertOnDuplicateUpdate(string $pool, string $table, array $fields, array $rows)
    {
        $db = Db::connection($pool);
        DbUtil::batchInsertOnDuplicateUpdate($db, $table, $fields, $rows);
    }

    protected function interact(InputInterface $input, OutputInterface $output)
    {
        $actions_available = $this->listActionsAvailable();
        if (empty($input->getArgument('action'))) {
            $this->output->error('You need to set action name to execute');
            $this->output->writeln('actions available:');
            foreach ($actions_available as $action) {
                $rc = new ReflectionMethod(static::class, $action . 'Action');
                $message = '    ' . $action;
                foreach ($rc->getParameters() as $param) {
                    $message .= ' ';
                    if ($param->isOptional()) {
                        $message .= '[';
                    }
                    $message .= '--' . $param->name . '=';
                    if ($param->isDefaultValueAvailable()) {
                        $message .= $param->getDefaultValue();
                    }
                    if ($param->isOptional()) {
                        $message .= ']';
                    } else {
                        $message .= ' ';
                    }
                }
                $this->output->writeln($message);
            }
            exit(1);
        }
    }

    private function listActionsAvailable()
    {
        $rc = new ReflectionClass(static::class);
        $actions_available = [];
        foreach ($rc->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if (substr($method->name, -6) === 'Action') {
                $actions_available[] = substr($method->name, 0, -6);
            }
        }

        return $actions_available;
    }
}
