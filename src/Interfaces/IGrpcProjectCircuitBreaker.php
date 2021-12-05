<?php


namespace Hyperf\Extend\Interfaces;

/**
 * 外部接入 grpc 服务时，可提供一个熔断器，以对项目做动态熔断
 */
interface IGrpcProjectCircuitBreaker
{
    /**
     * 判断一个项目是否处于熔断状态
     * @param string $project
     * @return bool
     */
    function isProjectBroken(string $project): bool;
}
