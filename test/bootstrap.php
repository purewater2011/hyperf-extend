<?php

declare(strict_types=1);

/**
 * This file is part of hyperf server projects.
 */
use Hyperf\Di\Container;
use Test\Config\ConfigFactory;

error_reporting(E_ERROR);
// Ensure that composer has installed all dependencies
if (!file_exists(dirname(__DIR__) . '/composer.lock')) {
    exit("Dependencies must be installed using composer:\n\nphp composer.phar install --dev\n\n"
        . "See http://getcomposer.org for help with installing composer\n");
}

!defined('BASE_PATH') && define('BASE_PATH', dirname(__DIR__, 1));

$autoloader = require BASE_PATH . '/vendor/autoload.php';

/** @var Container $container */
$container = require __DIR__ . '/Config/container.php';
$container->set(\Hyperf\Contract\ConfigInterface::class, (new ConfigFactory())($container));
