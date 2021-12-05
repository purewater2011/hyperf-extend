<?php

declare(strict_types=1);
/**
 * This file is part of hyperf server projects.
 */
namespace Hyperf\Extend\Overrides;

use Hyperf\Extend\Interfaces\IGrpcProjectCircuitBreaker;
use Hyperf\Extend\Utils\ConfigUtil;

class GrpcProjectCircuitBreaker implements IGrpcProjectCircuitBreaker
{
    public function isProjectBroken(string $project): bool
    {
        return in_array(
            $project,
            ConfigUtil::getCommonConfig('grpc_forbidden', [])
        );
    }
}
