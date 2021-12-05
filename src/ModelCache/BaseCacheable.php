<?php

declare(strict_types=1);
/**
 * This file is part of hyperf server projects.
 */
namespace Hyperf\Extend\ModelCache;

use Hyperf\ModelCache\Cacheable;
use Hyperf\Utils\ApplicationContext;

trait BaseCacheable
{
    use Cacheable;

    public function newModelQuery()
    {
        $builder = parent::newModelQuery();
        $class = get_called_class();
        $builder->macro('firstWithCache', function ($builder, $ttl = null) use ($class) {
            $container = ApplicationContext::getContainer();
            $manager = $container->get(BaseManager::class);
            return $manager->firstWithCache($builder, $class, $ttl);
        });
        return $builder;
    }

    /**
     * @param array $attributes
     * @param int $ttl
     * @param string $order
     * @return static
     */
    public static function findByAttributesWithCache($attributes, $ttl = null, $order = null)
    {
        $container = ApplicationContext::getContainer();
        $manager = $container->get(BaseManager::class);
        return $manager->findByAttributesWithCache($attributes, $order, static::class, $ttl);
    }

    /**
     * @param array $attributes
     * @param int $ttl
     * @param string $order
     * @param string $limit
     * @return static[]
     */
    public static function findAllByAttributesWithCache($attributes, $ttl = null, $order = null, $limit = null)
    {
        $container = ApplicationContext::getContainer();
        $manager = $container->get(BaseManager::class);
        return $manager->findAllByAttributesWithCache($attributes, $order, $limit, static::class, $ttl);
    }

    /**
     * @param string $sql
     * @param int $ttl
     * @param array $params
     * @return static
     */
    public static function findBySqlWithCache($sql, $ttl = null, $params = [])
    {
        $container = ApplicationContext::getContainer();
        $manager = $container->get(BaseManager::class);
        $models = $manager->findAllBySqlWithCache($sql, static::class, $ttl, $params);
        return !empty($models) ? $models[0] : null;
    }

    /**
     * @param string $sql
     * @param int $ttl
     * @param array $params
     * @return static[]
     */
    public static function findAllBySqlWithCache($sql, $ttl = null, $params = [])
    {
        $container = ApplicationContext::getContainer();
        $manager = $container->get(BaseManager::class);
        return $manager->findAllBySqlWithCache($sql, static::class, $ttl, $params);
    }
}
