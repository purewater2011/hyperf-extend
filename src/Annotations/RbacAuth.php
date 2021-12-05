<?php

declare(strict_types=1);
/**
 * This file is part of hyperf server projects.
 */
namespace Hyperf\Extend\Annotations;

use Hyperf\Di\Annotation\AbstractAnnotation;

/**
 * @Annotation
 * @Target({"METHOD", "CLASS"})
 */
class RbacAuth extends AbstractAnnotation
{
    /**
     * @var array
     */
    public $allow_ips = [];

    /**
     * @var bool
     */
    public $skip_auth = false;
}
