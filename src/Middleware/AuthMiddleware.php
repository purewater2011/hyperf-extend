<?php

declare(strict_types=1);
/**
 * This file is part of hyperf server projects.
 */
namespace Hyperf\Extend\Middleware;

use Hyperf\Di\Annotation\AnnotationCollector;
use Hyperf\Extend\Interfaces\RabcInterface;
use Hyperf\HttpMessage\Stream\SwooleStream;
use Hyperf\HttpServer\Router\Dispatched;
use Hyperf\Utils\Context;
use Hyperf\Extend\Annotations\RbacAuth;
use Hyperf\Extend\Utils\ENVUtil;
use Hyperf\Extend\Utils\ClientInfoUtil;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class AuthMiddleware implements MiddlewareInterface
{
    private $method_annotations = [];

    private $class_annotations = [];

    public function __construct()
    {
        foreach (AnnotationCollector::getClassByAnnotation(RbacAuth::class) as $class => $annotion) {
            $this->class_annotations[$class] = $annotion;
        }
        foreach (AnnotationCollector::getMethodByAnnotation(RbacAuth::class) as $annotion_info) {
            $key = $annotion_info['class'] . '::' . $annotion_info['method'];
            $this->method_annotations[$key] = $annotion_info['annotation'];
        }
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // check whether should ignore this request
        /** @var Dispatched $dispatched */
        $dispatched = $request->getAttribute(Dispatched::class);
        $auth_approved = true;
        if ($dispatched->isFound() && is_array($dispatched->handler->callback)) {
            $class = $dispatched->handler->callback[0] ?? null;
            $method = $dispatched->handler->callback[1] ?? null;
            // 对那些非标准的接口路由请求，不做访问权限控制
            if (!empty($class) && !empty($method)) {
                $method_annotation_key = $class . '::' . $method;
                /** @var RbacAuth $annotion */
                $annotion = $this->method_annotations[$method_annotation_key] ?? null;
                if (empty($annotion)) {
                    $annotion = $this->class_annotations[$class] ?? null;
                }
                if (empty($annotion)) {
                    // 接口默认需要进行 RABC 授权
                    if (!$this->shouldApproveRequest($request)) {
                        $auth_approved = false;
                    }
                } elseif (!$this->isRabcAuthSkipped($annotion) && !$this->shouldApproveRequest($request)) {
                    $auth_approved = false;
                }
            }
        }
        if (ENVUtil::isRunningUnitTests()) {
            $auth_approved = true;
        }
        if (!$auth_approved) {
            $response = Context::get(ResponseInterface::class);
            return $response->withBody(new SwooleStream(json_encode([
                'message' => 'Request rabc auth failed',
                'code' => 403,
            ])));
        }
        return $handler->handle($request);
    }

    public function checkIpValid($ip, $allow_ip)
    {
        if (!$allow_ip || !$ip) {
            return false;
        }
        $segment_info = explode('/', $allow_ip);
        if (count($segment_info) < 2) {
            if ($allow_ip == $ip) {
                return true;
            }
            return false;
        }
        $begin_ip_array = explode('.', $segment_info[0]);
        $mask = intval($segment_info[1]);
        $end_ip = [];
        foreach ($begin_ip_array as $ip_key => $item) {
            $begin_flag = 8 * ($ip_key); //0   8   16  24
            $end_flag = 8 * ($ip_key + 1); //8   16  24  32
            $decbin_item = str_pad(decbin($item), 8, '0', STR_PAD_LEFT);
            $end_ip[] = $mask >= $end_flag ? $item :
                ($mask > $begin_flag ? bindec(str_pad(substr($decbin_item, 0, $mask - $begin_flag), 8, '1', STR_PAD_RIGHT)) :
                    ($ip_key <= 2 ? pow(2, 8) - 1 : pow(2, 8) - 1));
        }
        $begin_ip = $segment_info[0];
        $end_ip = join('.', $end_ip);

        $ip = bindec(decbin(ip2long($ip)));
        $begin_ip = bindec(decbin(ip2long($begin_ip)));
        $end_ip = bindec(decbin(ip2long($end_ip)));

        if ($ip >= $begin_ip && $ip <= $end_ip) {
            return true;
        }
        return false;
    }

    private function isRabcAuthSkipped(?RbacAuth $annotion)
    {
        if (empty($annotion)) {
            return true;
        }
        if ($annotion->skip_auth === true) {
            return true;
        }
        if ($annotion->allow_ips) {
            $ip = ClientInfoUtil::getRemoteAddress();
            foreach ($annotion->allow_ips as $allow_ip) {
                if ($this->checkIpValid($ip, $allow_ip)) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * @param \Hyperf\HttpMessage\Server\Request $request
     * @return bool
     */
    private function shouldApproveRequest($request)
    {
        if (ENVUtil::isRunningUnitTests()) {
            return true;
        }
        if (ENVUtil::isDev() && $request->getServerParams()['remote_addr'] === '127.0.0.1') {
            return true;
        }
        $token = '';
        if ($request->getHeader('x-token')) {
            $token = $request->getHeader('x-token')[0];
        }
        if (!$token) {
            return false;
        }
        // check permission
        $request_path = $request->getUri()->getPath();

        /** @var RabcInterface $rabc */
        $rabc = make(RabcInterface::class);
        if ($rabc->checkPermission($request_path)) {
            return true;
        }
        return false;
    }
}
