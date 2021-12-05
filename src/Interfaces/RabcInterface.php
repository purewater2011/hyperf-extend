<?php


namespace Hyperf\Extend\Interfaces;


interface RabcInterface
{
    public function checkPermission($param): bool;
}
