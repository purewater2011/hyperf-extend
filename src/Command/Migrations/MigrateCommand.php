<?php

declare(strict_types=1);
/**
 * This file is part of hyperf server projects.
 */
namespace Hyperf\Extend\Command\Migrations;

use Hyperf\Contract\ConfigInterface;
use Hyperf\Utils\ApplicationContext;

class MigrateCommand extends \Hyperf\Database\Commands\Migrations\MigrateCommand
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
            $this->prepareDatabase();
            // Next, we will check to see if a path option has been defined. If it has
            // we will use the path relative to the root of this installation folder
            // so that migrations may be run for any path within the applications.
            $this->migrator->setOutput($this->output)
                ->run($this->getMigrationPaths(), [
                    'pretend' => $this->input->getOption('pretend'),
                    'step' => $this->input->getOption('step'),
                ]);
            // Finally, if the "seed" option has been given, we will re-run the database
            // seed task to re-populate the database, which is convenient when adding
            // a migration and a seed at the same time, as it is only this command.
            if ($this->input->getOption('seed') && !$this->input->getOption('pretend')) {
                $this->call('db:seed', ['--force' => true]);
            }
        }
    }
}
