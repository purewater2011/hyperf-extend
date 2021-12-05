<?php

declare(strict_types=1);
/**
 * This file is part of hyperf server projects.
 */
namespace Hyperf\Extend\Model;

trait ParamAndPostProcessorTrait
{
    /**
     * @var callable
     */
    private $post_processor;

    /**
     * @var callable
     */
    private $param_processor;

    /**
     * 设置数据读取结束之后的后期处理函数.
     */
    public function setPostProcessor(callable $processor)
    {
        $this->post_processor = $processor;
    }

    /**
     * 设置参数前期处理函数.
     */
    public function setParamProcessor(callable $processor)
    {
        $this->param_processor = $processor;
    }
}
