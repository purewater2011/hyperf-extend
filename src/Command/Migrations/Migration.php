<?php

declare(strict_types=1);
/**
 * This file is part of hyperf server projects.
 */
namespace Hyperf\Database\Migrations;

use Hyperf\Extend\Command\BaseMigration;

abstract class Migration extends BaseMigration
{
    /**
     * Enables, if supported, wrapping the migration within a transaction.
     *
     * @var bool
     */
    public $withinTransaction = true;

    /**
     * The name of the database connection to use.
     *
     * @var string
     */
    protected $connection = 'default';

    /**
     * Get the migration connection name.
     *
     * @return string
     */
    public function getConnection()
    {
        return $this->connection;
    }

    public function setConnection($connection)
    {
        $this->connection = $connection;
    }
}
