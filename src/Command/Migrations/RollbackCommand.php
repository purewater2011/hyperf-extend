<?php

declare(strict_types=1);
/**
 * This file is part of hyperf server projects.
 */
namespace Hyperf\Extend\Command\Migrations;

use Hyperf\Contract\ConfigInterface;
use Hyperf\Utils\ApplicationContext;

class RollbackCommand extends \Hyperf\Database\Commands\Migrations\RollbackCommand
{
    public function handle()
    {
        if (!$this->confirmToProceed()) {
            return;
        }

        $container = ApplicationContext::getContainer();
        $config = $container->get(ConfigInterface::class);
        foreach ($config->get('databases') as $database => $v) {
            $this->input->setOption('database', $database);

            $this->migrator->setConnection($this->input->getOption('database') ?? 'default');

            $this->migrator->setOutput($this->output)->rollback(
                $this->getMigrationPaths(),
                [
                    'pretend' => $this->input->getOption('pretend'),
                    'step' => (int) $this->input->getOption('step'),
                ]
            );
        }
    }
}
