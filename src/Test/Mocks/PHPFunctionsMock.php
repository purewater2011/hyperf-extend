<?php

declare(strict_types=1);
/**
 * This file is part of hyperf server projects.
 */
namespace Hyperf\Extend\Test\Mocks;

use Hyperf\Di\Container;
use Hyperf\Utils\ApplicationContext;
use Hyperf\Extend\Utils\PHPFunctions;
use Mockery;
use Mockery\MockInterface;

trait PHPFunctionsMock
{
    /**
     * @var MockInterface
     */
    protected $php_functions_mock;

    /**
     * @var float
     */
    private $mocked_micro_timestamp = 0;

    protected function initPHPFunctionsMock()
    {
        $this->mocked_micro_timestamp = microtime(true);
        $this->php_functions_mock = Mockery::mock(PHPFunctions::class);
        $this->php_functions_mock->shouldReceive('date')->passthru();
        $this->php_functions_mock->shouldReceive('sleep')->andReturnUsing(function ($seconds) {
            $this->mocked_micro_timestamp += $seconds;
        })->byDefault();
        $this->php_functions_mock->shouldReceive('microtime')->andReturnUsing(function () {
            return $this->mocked_micro_timestamp;
        })->byDefault();
        $this->php_functions_mock->shouldReceive('time')->andReturnUsing(function () {
            return intval($this->mocked_micro_timestamp);
        })->byDefault();
        /** @var Container $container */
        $container = ApplicationContext::getContainer();
        $container->set(PHPFunctions::class, $this->php_functions_mock);
    }

    protected function closePHPFunctionsMock()
    {
        /** @var Container $container */
        $container = ApplicationContext::getContainer();
        $container->set(PHPFunctions::class, new PHPFunctions());
    }

    protected function mockTime($time)
    {
        if (is_string($time)) {
            $time = strtotime($time);
        }
        $this->mocked_micro_timestamp = $time;
    }
}
