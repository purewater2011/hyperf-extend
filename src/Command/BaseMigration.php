<?php

declare(strict_types=1);
/**
 * This file is part of hyperf server projects.
 */
namespace Hyperf\Extend\Command;

use Hyperf\Contract\ConfigInterface;
use Hyperf\Utils\ApplicationContext;
use Hyperf\Extend\Utils\Util;

abstract class BaseMigration
{
    /**
     * Enables, if supported, wrapping the migration within a transaction.
     *
     * @var bool
     */
    public $withinTransaction = true;

    /**
     * The name of the database connection to use.
     * @var string
     */
    protected $connection = 'default';

    public function __construct()
    {
        $params = $_SERVER['argv'];
        $is_lock_db = false;
        if (is_array($params)) {
            foreach ($params as $param) {
                if (strpos($param, '--database') === false) {
                    continue;
                }
                $connection = str_replace('--database=', '', $param);
                $key = 'databases.' . $connection;
                $container = ApplicationContext::getContainer();
                $config = $container->get(ConfigInterface::class);
                if ($config->has($key)) { // 锁定指定database运行migrate
                    $this->connection = $connection;
                    $is_lock_db = true;
                }
                break;
            }
        }
        if (!$is_lock_db) { // 根据当前语种执行对应数据库的migrate
            $language_code = Util::currentLanguageCode();
            if ($language_code) {
                if ($language_code == 'hant') {
                    $language_code = 'cn';
                }
                $container = ApplicationContext::getContainer();
                $config = $container->get(ConfigInterface::class);
                $key = 'databases.' . $this->connection . '_' . $language_code;
                if ($config->has($key)) {
                    $this->connection = $this->connection . '_' . $language_code;
                }
            }
        }
    }

    /**
     * Get the migration connection name.
     *
     * @return string
     */
    public function getConnection()
    {
        return $this->connection;
    }
}
