<?php

declare(strict_types=1);
/**
 * This file is part of hyperf server projects.
 */
namespace Hyperf\Extend\Model;

use Hyperf\Extend\Model\BaseModelWithoutDatabase;
use Hyperf\Extend\Utils\Aliyun\MQ\Response\MQConsumeMessageItem;

abstract class BaseModelMQMessage extends BaseModelWithoutDatabase
{
    /**
     * 进行 MQ 消费时，获取到的 MQ 消息信息.
     * @var ?MQConsumeMessageItem
     */
    protected $mq_message_item;

    abstract public function getTopic(): string;

    abstract public function getTag(): string;

    /**
     * 设定本消息对应的所有消费者 group id
     * 设计该函数是为了规范 MQ 消息消费，需要消费 MQ 消息时，必须把分组 ID 先注册进来.
     * @return string[]
     */
    abstract public function getConsumerGroupIds(): array;

    public function setMqMessageItem(MQConsumeMessageItem $item)
    {
        $this->mq_message_item = $item;
    }

    public function getMqMessageItem(): ?MQConsumeMessageItem
    {
        return $this->mq_message_item;
    }
}
