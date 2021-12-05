<?php

declare(strict_types=1);
/**
 * This file is part of hyperf server projects.
 */

namespace Hyperf\Extend\Middleware;

use Hyperf\Di\Annotation\AnnotationCollector;
use Hyperf\HttpMessage\Stream\SwooleStream;
use Hyperf\HttpServer\Router\Dispatched;
use Hyperf\Utils\Context;
use Hyperf\Extend\Compatibility\HttpRequest;
use Hyperf\Extend\Annotations\Rsa;
use Hyperf\Extend\Utils\RSA as HyperfRSA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class RsaMiddleware implements MiddlewareInterface
{
    private $method_annotations = [];

    private $class_annotations = [];

    public function __construct()
    {
        foreach (AnnotationCollector::getClassesByAnnotation(Rsa::class) as $class => $annotion) {
            $this->class_annotations[$class] = $annotion;
        }
        foreach (AnnotationCollector::getMethodsByAnnotation(Rsa::class) as $annotion_info) {
            $key = $annotion_info['class'] . '::' . $annotion_info['method'];
            $this->method_annotations[$key] = $annotion_info['annotation'];
        }
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $is_rsa_enabled = false;
        /** @var Dispatched $dispatched */
        $dispatched = $request->getAttribute(Dispatched::class);
        if ($dispatched->isFound() && is_array($dispatched->handler->callback)) {
            $class = $dispatched->handler->callback[0] ?? null;
            $method = $dispatched->handler->callback[1] ?? null;
            if (!empty($class) && !empty($method)) {
                $method_annotation_key = $class . '::' . $method;
                /** @var Rsa $annotion */
                $annotion = $this->method_annotations[$method_annotation_key] ?? null;
                if (empty($annotion)) {
                    $annotion = $this->class_annotations[$class] ?? null;
                }
                if (!empty($annotion)) {
                    if ($annotion->enable) {
                        $is_rsa_enabled = true;
                    }
                }
            }
        }
        if ($is_rsa_enabled) {
            $s = HttpRequest::current()->post('s');
            $data = HttpRequest::current()->post('data');
            if (empty($s) && empty($data)) {
                parse_str($request->getBody()->getContents(), $post_arr);
                if ($post_arr) {
                    $s = $post_arr['s'] ?? null;
                    $data = $post_arr['data'] ?? null;
                }
            }
            if (empty($s) || empty($data) || !is_numeric($s)) {
                $response = Context::get(ResponseInterface::class);
                return $response->withBody(new SwooleStream(json_encode([
                    'message' => 'post params error',
                    'code' => 403,
                ])));
            }

            $offset = $s % 64 * -1;
            $base64_str = HyperfRSA::doBase64Offset($data, $offset);
            $json_str = HyperfRSA::private_decrypt(base64_decode($base64_str));
            //兼容解出来的数据后面有特殊字符
            $regex_pattern = '/\x{0000}/u';
            $json_str = preg_replace($regex_pattern, '', $json_str);
            $post = @json_decode($json_str);
            if ($post === false || $post === null) {
                $response = Context::get(ResponseInterface::class);
                return $response->withBody(new SwooleStream(json_encode([
                    'message' => 'rsa decode error',
                    'code' => 403,
                ])));
            }
            Context::set('RSA_DECODE_DATA', $post);
        }
        return $handler->handle($request);
    }
}
