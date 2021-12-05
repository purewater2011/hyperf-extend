<?php

declare(strict_types=1);
/**
 * This file is part of hyperf server projects.
 */
namespace Hyperf\Extend\Model;

class BaseModelSplit256 extends BaseModel
{
    protected $suffix = '00';

    public function getTable()
    {
        return parent::getTable() . '_' . $this->suffix;
    }

    public function setTable($table)
    {
        $table_without_suffix = $table;
        if (preg_match('#_[0-9a-f]{2}$#', $table)) {
            $table_without_suffix = substr($table, 0, strlen($table) - 3);
            $this->suffix = substr($table, -2, 2);
        }
        return parent::setTable($table_without_suffix);
    }

    public function newModelQuery()
    {
        $builder = parent::newModelQuery();
        $builder->macro('suffix', function ($instance, $suffix) {
            $this->suffix = $suffix;
            // 更新 query 当中的表名
            $instance->setModel($instance->getModel());
            return $instance;
        });
        return $builder;
    }

    public function saveWithSuffixValue($suffix_value)
    {
        $this->suffix = sprintf('%02x', $suffix_value % 256);
        return parent::save();
    }

    public static function generateSuffix($suffix_value)
    {
        return sprintf('%02x', $suffix_value % 256);
    }
}
