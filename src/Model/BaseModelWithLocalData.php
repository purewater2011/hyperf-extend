<?php

declare(strict_types=1);
/**
 * This file is part of hyperf server projects.
 */
namespace Hyperf\Extend\Model;

use Hyperf\Database\Model\Builder;
use Hyperf\Database\Model\Collection;
use Hyperf\Database\Model\Model;

/**
 * 使用本地数据源的模型.
 * @method static Builder select($columns = ['*'])
 * @method static Builder where($column, $operator = null, $value = null, $boolean = 'and')
 * @method static Builder whereIn($column, $values, $boolean = 'and', $not = false)
 * @method static Collection get($columns = ['*'])
 * @method static static|null find($id, $columns = ['*'])
 */
abstract class BaseModelWithLocalData extends Model
{
    /**
     * @var array 本地数据
     */
    protected $local_data;

    protected function newBaseQueryBuilder(): LocalDataBuilder
    {
        return new LocalDataBuilder($this->local_data);
    }
}
