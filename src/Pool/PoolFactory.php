<?php

declare(strict_types=1);
/**
 * This file is part of hyperf server projects.
 */
namespace Hyperf\Extend\Pool;

class PoolFactory extends \Hyperf\DbConnection\Pool\PoolFactory
{
    public function setPoolNull($name)
    {
        if (isset($this->pools[$name])) {
            $pool = $this->pools[$name];
            $pool->flush();
            unset($this->pools[$name]);
        }
    }
}
