<?php

declare(strict_types=1);
/**
 * This file is part of hyperf server projects.
 */
namespace Hyperf\Extend\Model;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Google\Protobuf\Internal\Message;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Database\Model\Builder;
use Hyperf\Database\Model\Collection;
use Hyperf\DbConnection\Db;
use Hyperf\DbConnection\Model\Model;
use Hyperf\Utils\ApplicationContext;
use Hyperf\Utils\Contracts\Arrayable;
use Hyperf\Extend\ModelCache\BaseCacheable;
use Hyperf\Extend\Utils\Util;
use stdClass;

/**
 * @method static Builder select(array|mixed $columns = ['*'])
 * @method static Builder selectRaw(string $expression)
 * @method static Builder whereKey(mixed $id)
 * @method static Builder whereKeyNot(mixed $id)
 * @method static Builder where(array|\Closure|string $column, null|mixed $operator = null, null|mixed $value = null, string $boolean = 'and')
 * @method static Builder orWhere(array|\Closure|string $column, null|mixed $operator = null, null|mixed $value = null)
 * @method static Builder whereIn(string $column, mixed $values, string $boolean = 'and', bool $not = false)
 * @method static Builder whereNotIn(string $column, mixed $values, string $boolean = 'and')
 * @method static Builder whereNull(string $column, string $boolean = 'and', bool $not = false)
 * @method static Builder whereNotNull(string $column, string $boolean = 'and')
 * @method static Builder limit(int $value)
 * @method static null|Collection|static find(array|int|string $id, array $columns = ['*'])
 * @method static Collection findMany(array|Arrayable $ids, array $columns = ['*'])
 * @method static Collection|static findOrNew(array|int|string $id, array $columns = ['*'])
 * @method static static firstOrNew(array $attributes, array $values = [])
 * @method static static firstOrCreate(array $attributes, array $values = [])
 */
class BaseModel extends Model implements \Hyperf\ModelCache\CacheableInterface
{
    use BaseCacheable;

    public $use_cache = false;

    /**
     * @param int $id
     * @return static
     */
    public static function findById($id)
    {
        return static::query()->whereKey($id)->first();
    }

    /**
     * @param int[] $ids
     * @return static[]
     */
    public static function findAllById($ids)
    {
        return static::query()->whereKey($ids)->getModels();
    }

    /**
     * @param array $attributes
     * @param string $order
     * @return static
     */
    public static function findByAttributes($attributes, $order = null)
    {
        if (empty($attributes)) {
            return null;
        }
        $query = static::query();
        self::appendAttributesMatchToQuery($query, $attributes);
        self::appendOrderToQuery($query, $order);
        return $query->first();
    }

    /**
     * @param array $attributes
     * @param string $order
     * @param int $limit
     * @return static[]
     */
    public static function findAllByAttributes($attributes, $order = null, $limit = null)
    {
        if (empty($attributes)) {
            return null;
        }
        $query = static::query();
        self::appendAttributesMatchToQuery($query, $attributes);
        self::appendOrderToQuery($query, $order);
        if (!empty($limit)) {
            $query->limit($limit);
        }
        return $query->getModels();
    }

    /**
     * @param string $sql
     * @param array $params
     * @return static
     */
    public static function findBySql($sql, $params = [])
    {
        $models = self::findAllBySql($sql, $params);
        return !empty($models) ? $models[0] : null;
    }

    /**
     * @param string $sql
     * @param array $params
     * @return static[]
     */
    public static function findAllBySql($sql, $params = [])
    {
        /** @var Model $instance */
        $instance = new static();

        $pool_name = $instance->getConnectionName();
        $rows = Db::connection($pool_name)->select($sql, $params);
        $models = [];
        foreach ($rows as $row) {
            $model = $instance->newFromBuilder(
                json_decode(json_encode($row), true),
                $pool_name
            );
            $models[] = $model;
        }
        return $models;
    }

    /**
     * @param array $attributes
     * @return bool
     */
    public function saveAttributes($attributes)
    {
        foreach ($attributes as $key => $value) {
            $this->setAttribute($key, $value);
        }
        return $this->save();
    }

    public function fillWithProtobufMessage(Message $message)
    {
        if ($message instanceof Message) {
            if (extension_loaded('protobuf')) {
                // protobuf.so 扩展，内部函数实现与 php 库存在差异
                $attributes = json_decode($message->serializeToJsonString(true), true);
                foreach ($attributes as $field_name => $value) {
                    if ($this->isFillable($field_name)) {
                        $this->setAttribute($field_name, $value);
                    }
                }
            } else {
                $pool = \Google\Protobuf\Internal\DescriptorPool::getGeneratedPool();
                $descriptor = $pool->getDescriptorByClassName(get_class($message));
                foreach ($descriptor->getField() as $field) {
                    /** @var \Google\Protobuf\Internal\FieldDescriptor $field */
                    $field_name = $field->getName();
                    if ($this->isFillable($field_name)) {
                        $getter = $field->getGetter();
                        $this->setAttribute($field_name, $message->{$getter}());
                    }
                }
            }
        }
    }

    /**
     * @param $class
     * @return Message
     */
    public function convertToProtobufMessage($class)
    {
        /** @var Message $instance */
        $instance = new $class();
        if (extension_loaded('protobuf')) {
            // protobuf.so 扩展，内部函数实现与 php 库存在差异
            $attributes = $this->attributesToArray();
            foreach ($this->getDates() as $field) {
                if (empty($attributes[$field])) {
                    unset($attributes[$field]);
                } elseif (is_string($attributes[$field])) {
                    $attributes[$field] = strtotime($attributes[$field]);
                } elseif ($attributes[$field] instanceof Carbon) {
                    $attributes[$field] = $attributes[$field]->timestamp;
                }
            }
            $instance->mergeFromJsonString(json_encode($attributes, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE), true);
        } else {
            $pool = \Google\Protobuf\Internal\DescriptorPool::getGeneratedPool();
            $descriptor = $pool->getDescriptorByClassName(get_class($instance));
            foreach ($descriptor->getField() as $field) {
                /** @var \Google\Protobuf\Internal\FieldDescriptor $field */
                $field_name = $field->getName();
                if (empty($this->{$field_name})) {
                    continue;
                }
                $setter = $field->getSetter();
                if (!empty($this->{$field_name})) {
                    $value = $this->{$field_name};
                    if ($field->getType() === 3) { // 预期是整形
                        if ($this->{$field_name} instanceof Carbon) {
                            $value = $this->{$field_name}->timestamp;
                        } elseif ($value === '0000-00-00 00:00:00') {
                            $value = 0;
                        } else {
                            $value = intval($value);
                        }
                    }
                    $instance->{$setter}($value);
                }
            }
        }
        return $instance;
    }

    /**
     * 子类需要重载该方法以返回模型类在被转换成json数据时，需要输出的字段列表.
     * @return array
     */
    public function attributesForJson()
    {
        return [];
    }

    /**
     * 把该模型类转换为json数据.
     * @return stdClass
     */
    public function asJsonObject()
    {
        $result = new stdClass();
        foreach ($this->attributesForJson() as $attribute) {
            $result->{$attribute} = self::asJsonObjectForValue($this->{$attribute});
        }

        return $result;
    }

    /**
     * @param $value
     * @return array|bool|int|string
     */
    public static function asJsonObjectForValue($value)
    {
        if ($value === true) {
            return true;
        }
        if ($value === false) {
            return false;
        }
        if (is_array($value) || is_a($value, 'stdClass')) {
            $result = [];
            foreach ($value as $key => $item) {
                $result[$key] = self::asJsonObjectForValue($item);
            }

            return $result;
        }
        if (is_string($value) || is_numeric($value)) {
            return $value;
        }
        /** @var BaseModel $value */
        if (is_a($value, 'BaseModel')) {
            return $value->asJsonObject();
        }
    }

    /**
     * 重载默认的 asDateTime，以更好的处理时间为空的情况.
     * @param mixed $value
     * @return CarbonInterface
     */
    protected function asDateTime($value)
    {
        if ($value === null) {
            return null;
        }
        if ($value === '0000-00-00 00:00:00') {
            return null;
        }
        return parent::asDateTime($value);
    }

    protected function addDateAttributesToArray(array $attributes)
    {
        foreach ($this->getDates() as $key) {
            if (!isset($attributes[$key])) {
                continue;
            }
            $datetime = $this->asDateTime($attributes[$key]);
            if (empty($datetime)) {
                unset($attributes[$key]);
            } elseif ($datetime instanceof Carbon) {
                $attributes[$key] = date('Y-m-d H:i:s', $datetime->timestamp);
            } else {
                $attributes[$key] = $datetime;
            }
        }
        return $attributes;
    }

    protected static function appendAttributesMatchToQuery(Builder $query, $attributes)
    {
        if (empty($attributes)) {
            return;
        }
        foreach ($attributes as $key => $value) {
            if (is_array($value)) {
                $query->whereIn($key, $value);
            } else {
                $query->where($key, $value);
            }
        }
    }

    protected static function appendOrderToQuery(Builder $query, $order)
    {
        if (empty($order)) {
            return;
        }
        foreach (explode(',', $order) as $order_item) {
            $order_item = trim($order_item);
            $segs = explode(' ', $order_item, 2);
            $direction = 'asc';
            if (count($segs) === 2) {
                $direction = $segs[1];
            }
            $query->orderBy($segs[0], $direction);
        }
    }
}
