<?php

declare(strict_types=1);
/**
 * This file is part of hyperf server projects.
 */
namespace Hyperf\Extend\ModelCache;

use Hyperf\Contract\ConfigInterface;
use Hyperf\DbConnection\Model\Model;
use Hyperf\ModelCache\Manager;
use Hyperf\Utils\ApplicationContext;
use Hyperf\Extend\Model\BaseModel;

class BaseManager extends Manager
{
    /**
     * Execute the query and get the first result from cache.
     *
     * @param array $columns
     * @param mixed $ttl
     * @return null|Model|object|static
     */
    public function firstWithCache(\Hyperf\Database\Model\Builder $builder, string $class, $ttl)
    {
        /** @var Model $instance */
        $instance = new $class();
        $name = $instance->getConnectionName();
        $app_name = ApplicationContext::getContainer()->get(ConfigInterface::class)->get('app_name');

        if ($handler = $this->handlers[$name] ?? null) {
            $params = $builder->getBindings();
            $sql = $builder->toSql();
            $params_encoded = json_encode($params);
            if ($params_encoded === false) {
                $params_encoded = serialize($params);
            }
            $key = md5($app_name . $name . $instance->getTable() . $sql . $params_encoded);
            $data = $handler->get($key);
            if ($data) {
                return $instance->newFromBuilder(
                    $this->getAttributes($handler->getConfig(), $instance, $data)
                );
            }

            // Fetch it from database, because it not exist in cache handler.
            if (is_null($data)) {
                $model = $builder->first();
                if ($model) {
                    if (is_null($ttl)) {
                        $ttl = $handler->getConfig()->getTtl();
                    }
                    $handler->set($key, $this->formatModel($model), $ttl);
                } else {
                    if (is_null($ttl)) {
                        $ttl = $handler->getConfig()->getEmptyModelTtl();
                    }
                    $handler->set($key, [], $ttl);
                }
                return $model;
            }

            // It not exist in cache handler and database.
            return null;
        }

        return $builder->first();
    }

    public function findByAttributesWithCache($attributes, $order, string $class, $ttl)
    {
        if (empty($attributes)) {
            return null;
        }
        /** @var BaseModel $class */
        $query = $class::query();
        $class::appendAttributesMatchToQuery($query, $attributes);
        $class::appendOrderToQuery($query, $order);
        return $this->firstWithCache($query, $class, $ttl);
    }

    public function findAllByAttributesWithCache($attributes, $order, $limit, string $class, $ttl)
    {
        /** @var BaseModel $class */
        /** @var Model $instance */
        $instance = new $class();
        $name = $instance->getConnectionName();
        $app_name = ApplicationContext::getContainer()->get(ConfigInterface::class)->get('app_name');

        if ($handler = $this->handlers[$name] ?? null) {
            $query = $class::query();
            $class::appendAttributesMatchToQuery($query, $attributes);
            $class::appendOrderToQuery($query, $order);
            if (!empty($limit)) {
                $query->limit($limit);
            }
            $sql = $query->toSql();
            $params = $query->getBindings();
            $params_encoded = json_encode($params);
            if ($params_encoded === false) {
                $params_encoded = serialize($params);
            }
            $key = md5($app_name . $name . $instance->getTable() . $sql . $params_encoded);
            $models = [];
            $cached_value = $handler->get($key) ?: [];
            if (!empty($cached_value) && !empty($cached_value[0])) {
                $cached_value = unserialize($cached_value[0]);
            } else {
                $cached_value = [];
            }
            if ($cached_value && is_array($cached_value)) {
                foreach ($cached_value as $item) {
                    $model = $instance->newFromBuilder(
                        $this->getAttributes($handler->getConfig(), $instance, $item)
                    );
                    $models[] = $model;
                }
                return $models;
            }

            // Fetch it from database, because it not exist in cache handler.
            if (empty($models)) {
                $models = $class::findAllByAttributes($attributes, $order, $limit);
                if (!empty($models)) {
                    if (is_null($ttl)) {
                        $ttl = $handler->getConfig()->getTtl();
                    }
                    $handler->set($key, [serialize($this->formatModels($models))], $ttl);
                } else {
                    if (is_null($ttl)) {
                        $ttl = $handler->getConfig()->getEmptyModelTtl();
                    }
                    $handler->set($key, [''], $ttl);
                }
                return $models ?: [];
            }

            // It not exist in cache handler and database.
            return [];
        }

        return $class::findAllByAttributes($attributes, $order, $limit);
    }

    public function findAllBySqlWithCache($sql, string $class, $ttl, $params = [])
    {
        /** @var BaseModel $class */
        /** @var Model $instance */
        $instance = new $class();
        $name = $instance->getConnectionName();
        $app_name = ApplicationContext::getContainer()->get(ConfigInterface::class)->get('app_name');

        if ($handler = $this->handlers[$name] ?? null) {
            $params_encoded = json_encode($params);
            if ($params_encoded === false) {
                $params_encoded = serialize($params);
            }
            $key = md5($app_name . $name . $instance->getTable() . $sql . $params_encoded);
            $models = [];
            $cached_value = $handler->get($key) ?: [];
            if (!empty($cached_value) && !empty($cached_value[0])) {
                $cached_value = unserialize($cached_value[0]);
            } else {
                $cached_value = [];
            }
            if ($cached_value && is_array($cached_value)) {
                foreach ($cached_value as $item) {
                    $model = $instance->newFromBuilder(
                        $this->getAttributes($handler->getConfig(), $instance, $item)
                    );
                    $models[] = $model;
                }
                return $models;
            }

            // Fetch it from database, because it not exist in cache handler.
            if (empty($models)) {
                $models = $class::findAllBySql($sql, $params);
                if (!empty($models)) {
                    if (is_null($ttl)) {
                        $ttl = $handler->getConfig()->getTtl();
                    }
                    $handler->set($key, [serialize($this->formatModels($models))], $ttl);
                } else {
                    if (is_null($ttl)) {
                        $ttl = $handler->getConfig()->getEmptyModelTtl();
                    }
                    $handler->set($key, [''], $ttl);
                }
                return $models ?: [];
            }

            // It not exist in cache handler and database.
            return [];
        }

        return $class::findAllBySql($sql, $params);
    }
}
