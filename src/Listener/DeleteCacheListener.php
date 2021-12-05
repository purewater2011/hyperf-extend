<?php

declare(strict_types=1);
/**
 * This file is part of hyperf server projects.
 */
namespace Hyperf\Extend\Listener;

use Hyperf\Database\Model\Events\Event;
use Hyperf\ModelCache\CacheableInterface;

class DeleteCacheListener extends \Hyperf\ModelCache\Listener\DeleteCacheListener
{
    public function process(object $event)
    {
        if ($event instanceof Event) {
            $model = $event->getModel();
            if ($model instanceof CacheableInterface && $model->use_cache) {
                $model->deleteCache();
            }
        }
    }
}
