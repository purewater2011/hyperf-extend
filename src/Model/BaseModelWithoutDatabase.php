<?php

declare(strict_types=1);
/**
 * This file is part of hyperf server projects.
 */
namespace Hyperf\Extend\Model;

use Hyperf\Di\ReflectionManager;
use JsonSerializable;
use Hyperf\Extend\Utils\ArrayUtil;
use ReflectionProperty;
use stdClass;
use Traversable;

/**
 * 不对接后端数据库存储的简易模型类
 * 1. 提供了构造函数方便对模型类做初始化
 * 2. 实现了 JsonSerializable 把模型类转为数组.
 */
class BaseModelWithoutDatabase implements JsonSerializable
{
    /**
     * 需要转换为整形的属性.
     * @var string[]
     */
    protected $int_attributes = [];

    /**
     * 需要转换为数字数组的属性.
     * @var string[]
     */
    protected $int_array_attributes = [];

    /**
     * 必须填充的属性，使用validate方法验证
     * @var string[]
     */
    protected $required_attributes = [];

    /**
     * @param array|stdClass|Traversable $attributes
     */
    public function __construct($attributes = [])
    {
        //未设置的key对应值保持null，在json_encode的时候不输出
        foreach ($attributes ?? [] as $key => $val) {
            if (property_exists($this, $key)) {
                if (in_array($key, $this->int_attributes)) {
                    $val = intval($val);
                } elseif (in_array($key, $this->int_array_attributes)) {
                    $val = ArrayUtil::convertToIntArray($val);
                }
                $this->{$key} = $val;
            }
        }
    }

    public function toArray(): array
    {
        $properties = ReflectionManager::reflectClass(static::class)->getProperties(ReflectionProperty::IS_PUBLIC);
        $result = [];
        foreach ($properties as $property) {
            $key = $property->getName();
            $val = $property->getValue($this);
            //json_encode的时候不输出减少不必要的存储
            if (is_null($val)) {
                continue;
            }
            if (in_array($key, $this->int_attributes)) {
                $val = intval($val);
            } elseif (in_array($key, $this->int_array_attributes)) {
                $val = ArrayUtil::convertToIntArray($val);
            }
            $result[$key] = $val;
        }
        return $result;
    }

    /**
     * 验证当前填充的数据是否符合规则.
     */
    public function validate(): bool
    {
        foreach ($this->required_attributes as $key) {
            if (!isset($this->{$key})) {
                return false;
            }
        }
        return true;
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
