<?php

declare(strict_types=1);
/**
 * This file is part of hyperf server projects.
 */
namespace Hyperf\Extend\Controller\Admin;

use Hyperf\Contract\ConfigInterface;
use Hyperf\Di\Annotation\AspectCollector;
use Hyperf\Utils\ApplicationContext;
use Hyperf\Extend\Controller\HttpBaseController;
use Hyperf\Extend\Utils\ENV;

/**
 * 获取项目运行状态的信息.
 */
class DevController extends HttpBaseController
{
    public function info()
    {
        $container = ApplicationContext::getContainer();
        $config = $container->get(ConfigInterface::class);
        return [
            'name' => $config->get('app_name'),
            'version' => $this->getVersion(),
            'asspects' => AspectCollector::get('classes', []),
            'env' => [
                'is_dev' => ENV::isDev(),
                'is_test' => ENV::isTest(),
                'is_pre' => ENV::isPre(),
                'app_env' => env('APP_ENV', 'dev'),
            ],
        ];
    }

    private function getVersion()
    {
        $file_path = BASE_PATH . '/version.log';
        if (file_exists($file_path)) {
            return trim(file_get_contents($file_path));
        }
    }
}
