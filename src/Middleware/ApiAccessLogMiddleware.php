<?php

declare(strict_types=1);
/**
 * This file is part of hyperf server projects.
 */
namespace Hyperf\Extend\Middleware;

use Hyperf\Di\Annotation\AnnotationCollector;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface as HttpResponse;
use Hyperf\HttpServer\Router\Dispatched;
use Hyperf\Utils\Context;
use Hyperf\Extend\IPIP\IPIP;
use Hyperf\Extend\Annotations\ApiAccessLog;
use Hyperf\Extend\Server\HttpServer;
use Hyperf\Extend\Utils\ENV;
use Hyperf\Extend\Utils\ENVUtil;
use Hyperf\Extend\Utils\Util;
use Hyperf\Extend\Utils\ClientInfoUtil;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class ApiAccessLogMiddleware implements MiddlewareInterface
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @var RequestInterface
     */
    protected $request;

    /**
     * @var HttpResponse
     */
    protected $response;

    private $class_annotations = [];

    private $method_annotations = [];

    public function __construct(ContainerInterface $container, HttpResponse $response, RequestInterface $request)
    {
        $this->container = $container;
        $this->response = $response;
        $this->request = $request;

        foreach (AnnotationCollector::getClassesByAnnotation(ApiAccessLog::class) as $class => $annotation) {
            $this->class_annotations[$class] = $annotation;
        }
        foreach (AnnotationCollector::getMethodsByAnnotation(ApiAccessLog::class) as $annotation) {
            $key = $annotation['class'] . '::' . $annotation['method'];
            $this->method_annotations[$key] = $annotation['annotation'];
        }
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $request_time = Context::get(HttpServer::CONTEXT_KEY_REQUEST_TIME_FLOAT, microtime(true));
        $handler_response = $handler->handle($request);
        $dispatched = $request->getAttribute(Dispatched::class);
        if (!$dispatched->isFound() || !is_array($dispatched->handler->callback)) {
            return $handler_response;
        }
        $class = $dispatched->handler->callback[0] ?? null;
        $method = $dispatched->handler->callback[1] ?? null;
        if (empty($class) || empty($method)) {
            return $handler_response;
        }
        if (ENVUtil::isRunningUnitTests()) {
            // 单元测试执行环境下，跳过 API 日志写入
            return $handler_response;
        }
        $method_annotation_key = $class . '::' . $method;
        /** @var ApiAccessLog $annotation */
        $annotation = $this->method_annotations[$method_annotation_key] ?? null;
        if (empty($annotation)) {
            $annotation = $this->class_annotations[$class] ?? null;
        }
        if ($annotation && $annotation->enable === false) {
            return $handler_response;
        }
        $log_line = $this->formatLogLine($request, $handler_response, $request_time);
        self::writeFile($this->getLogFilePath(), $log_line, FILE_APPEND);
        return $handler_response;
    }

    /**
     * @param string $filename 文件路径
     * @param string $data 要写入文件的内容
     * @param int $flags FILE_APPEND 表示追加到文件末尾
     * @param bool $auto_create_folder 如果文件所在的目录不存在，是否要进行创建
     * @return bool|int
     */
    public static function writeFile($filename, $data, $flags = 0, $auto_create_folder = true)
    {
        if ($auto_create_folder) {
            $is_write_success = self::writeFile($filename, $data, $flags, false);
            if ($is_write_success) {
                return true;
            }
            $folder_path = dirname($filename);
            if (!is_dir($folder_path)) {
                @mkdir($folder_path, 0777, true);
            }
        }
        return \Swoole\Coroutine::writeFile($filename, $data, $flags);
    }

    protected function getLogFilePath(): string
    {
        $folder_path = BASE_PATH . '/runtime/api_access_logs/';
        if (ENV::isPre()) {
            $folder_path = BASE_PATH . '/runtime/pre_logs/api_access_logs/';
        }
        return $folder_path . date('Y-m-d', time()) . '/' . date('H') . '.log';
    }

    protected function formatLogLine(ServerRequestInterface $request, ResponseInterface $response, float $request_time): string
    {
        $ip = ClientInfoUtil::getRemoteAddress();

        $query = $request->getQueryParams();

        $log_items = [
            date('Y-m-d H:i:s'),
            $request->getUri()->getPath(),
            $ip,
            IPIP::findCountryOrRegion($ip),
            json_encode($query, JSON_UNESCAPED_UNICODE),
            json_encode($request->getParsedBody(), JSON_UNESCAPED_UNICODE),
            sprintf('%.2f', (microtime(true) - $request_time) * 1000),
        ];
        if (ENV::isPre() || ENV::isDev()) { // 预发布环境
            $log_items[] = Util::getTraceId();
            $log_items[] = $response->getStatusCode();
            $log_items[] = $response->getBody();
        }
        return join("\t", $log_items) . "\n";
    }
}
