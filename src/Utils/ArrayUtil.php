<?php

declare(strict_types=1);
/**
 * This file is part of hyperf server projects.
 */
namespace Hyperf\Extend\Utils;

use Google\Protobuf\Internal\RepeatedField;

class ArrayUtil
{
    /**
     * 将数据转为int型数组.
     * @param array|int|string $data
     * @param bool $unique 是否去重
     * @param string $compare 比较规则 =,!=,>,>=,<,<=
     * @param int $num 比较的值
     * @return int[]
     */
    public static function convertToIntArray($data, bool $unique = false, string $compare = '', int $num = 0)
    {
        if (empty($data)) {
            return [];
        }
        if (is_int($data) || is_string($data)) {
            $data = [$data];
        }
        $result = [];
        foreach ($data as $item) {
            $item = intval($item);
            if ($compare == '=') {
                if ($item == $num) {
                    $result[] = $item;
                }
            } elseif ($compare == '!=') {
                if ($item != $num) {
                    $result[] = $item;
                }
            } elseif ($compare == '>') {
                if ($item > $num) {
                    $result[] = $item;
                }
            } elseif ($compare == '>=') {
                if ($item >= $num) {
                    $result[] = $item;
                }
            } elseif ($compare == '<') {
                if ($item < $num) {
                    $result[] = $item;
                }
            } elseif ($compare == '<=') {
                if ($item <= $num) {
                    $result[] = $item;
                }
            } else {
                $result[] = $item;
            }
        }
        if ($unique) {
            $result = array_values(array_unique($result));
        }
        return $result;
    }

    /**
     * 把一个数组按照一定大小进行再分组.
     * @return array[] 分组了之后的数组
     */
    public static function group(array $data, int $group_size): array
    {
        $result = [];
        if (empty($data)) {
            return $result;
        }
        $count = count($data);
        for ($i = 0; $i < $count; $i += $group_size) {
            $result[] = array_slice($data, $i, min($group_size, $count - $i));
        }
        return $result;
    }

    /**
     * 交换两个数组当中的值
     * @param $key1
     * @param $key2
     */
    public static function swap(array &$data, $key1, $key2)
    {
        $temp = $data[$key1] ?? null;
        $data[$key1] = $data[$key2] ?? null;
        $data[$key2] = $temp;
    }

    public static function convertProtobufRepeatedFieldToArray(RepeatedField $field): array
    {
        $result = [];
        foreach ($field as $v) {
            $result[] = $v;
        }
        return $result;
    }
}
