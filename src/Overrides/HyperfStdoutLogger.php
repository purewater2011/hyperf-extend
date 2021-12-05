<?php

declare(strict_types=1);
/**
 * This file is part of hyperf server projects.
 */
namespace Hyperf\Extend\Overrides;

use Hyperf\Framework\Logger\StdoutLogger;
use Hyperf\Extend\Utils\ENVUtil;

class HyperfStdoutLogger extends StdoutLogger
{
    public function debug($message, array $context = []): void
    {
        if (ENVUtil::isDev() || ENVUtil::isPre() || ENVUtil::isRunningUnitTests()) {
            // 去除框架内部发出的爆炸性干扰的 debug 日志
            if (preg_match('#^Event [^ ]+ handled by [^ ]+ listener\.$#', $message)) {
                return;
            }
        }
        if (!preg_match('#^[0-9- :]{19} #', $message)) {
            $message = date('Y-m-d H:i:s ') . $message;
        }
        parent::debug($message, $context);
    }

    public function info($message, array $context = []): void
    {
        if (!preg_match('#^[0-9- :]{19} #', $message)) {
            $message = date('Y-m-d H:i:s ') . $message;
        }
        parent::info($message, $context);
    }
}
