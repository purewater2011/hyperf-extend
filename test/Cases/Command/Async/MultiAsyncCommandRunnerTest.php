<?php

declare(strict_types=1);
/**
 * This file is part of hyperf server projects.
 */
namespace Test\Cases\Command\Async;

use Hyperf\Extend\Command\Async\MultiAsyncCommandRunner;
use PHPUnit\Framework\TestCase;
use Swoole\Coroutine;

/**
 * @internal
 * @coversNothing
 */
class MultiAsyncCommandRunnerTest extends TestCase
{
    public function testNormal()
    {
        Coroutine\run(function () {
            $runner = new MultiAsyncCommandRunner(5);
            for ($i = 0; $i < 8; ++$i) {
                $command = sprintf('echo "task %d start"; sleep 1; echo "task %d stop"; ', $i, $i);
                $runner->exec($command);
            }
            $runner->setProgressCallback(function (MultiAsyncCommandRunner $runner) {
                echo date('Y-m-d H:i:s'), ' ', $runner->getCommandsCompletedCount(), '/', 8, "\n";
            });
            $runner->wait();
            $this->assertCount(8, $runner->getCommandRunners());
        });
    }
}
