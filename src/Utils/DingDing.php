<?php

declare(strict_types=1);
/**
 * This file is part of hyperf server projects.
 */
namespace Hyperf\Extend\Utils;

class DingDing
{

    /**
     * 相关警报.
     */
    const GROUP_ALI_ALARM = 1;

    /**
     * 服务警报.
     */
    const GROUP_SERVICE = 2;

    /**
     * 钉钉群机器人token.
     */
    const GROUP_TOKENS = [
        self::GROUP_ALI_ALARM => 'token1',
        self::GROUP_SERVICE => 'token2',
    ];

    /**
     * 发送一条钉钉信息.
     * @param $message
     * @param $group_id
     * @param mixed $at_all
     * @param mixed $at_people
     * @deprecated 改为统一使用 sendMarkdown
     */
    public static function sendMessage($message, $group_id, $at_all = false, $at_people = '')
    {
        if (!isset(self::GROUP_TOKENS[$group_id])) {
            return;
        }
        $data = [
            'msgtype' => 'text',
            'text' => ['content' => $message],
            'at' => [
                'atMobiles' => [$at_people],
                'isAtAll' => $at_all,
            ],
        ];
        $data_string = json_encode($data);
        $webhook_url = 'https://oapi.dingtalk.com/robot/send?access_token=' . self::GROUP_TOKENS[$group_id];
        Http::post($webhook_url, $data_string, ['Content-Type' => 'application/json;charset=utf-8']);
    }

    /**
     * 发送一条 markdown 格式的钉钉信息.
     * @param $title
     * @param $message
     * @param mixed $at_all
     * @param mixed $at_people
     */
    public static function sendMarkdown($title, $message, int $group_id, $at_all = false, $at_people = '')
    {
        if (!isset(self::GROUP_TOKENS[$group_id])) {
            return;
        }
        $data = [
            'msgtype' => 'markdown',
            'markdown' => [
                'title' => $title,
                'text' => $message,
            ],
            'at' => ['isAtAll' => $at_all],
        ];
        if (!empty($at_people)) {
            $data['markdown']['text'] .= "\n\n @{$at_people}";
            $data['at']['atMobiles'] = [$at_people];
        }
        $data_string = json_encode($data);
        $webhook_url = 'https://oapi.dingtalk.com/robot/send?access_token=' . self::GROUP_TOKENS[$group_id];
        Http::post($webhook_url, $data_string, ['Content-Type' => 'application/json;charset=utf-8']);
    }
}
