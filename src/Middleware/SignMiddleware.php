<?php

declare(strict_types=1);
/**
 * This file is part of hyperf server projects.
 */
namespace Hyperf\Extend\Middleware;

use Hyperf\Di\Annotation\AnnotationCollector;
use Hyperf\Extend\Annotations\SignAuth;
use Hyperf\HttpMessage\Stream\SwooleStream;
use Hyperf\HttpServer\Router\Dispatched;
use Hyperf\Utils\Context;
use Hyperf\Extend\Compatibility\HttpRequest;
use Hyperf\Extend\Utils\ConfigUtil;
use Hyperf\Extend\Utils\ClientInfoUtil;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class SignMiddleware implements MiddlewareInterface
{
    const SIGN_SECRET = 'todo';

    private $method_annotations = [];

    private $class_annotations = [];

    public function __construct()
    {
        foreach (AnnotationCollector::getClassesByAnnotation(SignAuth::class) as $class => $annotion) {
            $this->class_annotations[$class] = $annotion;
        }
        foreach (AnnotationCollector::getMethodsByAnnotation(SignAuth::class) as $annotion_info) {
            $key = $annotion_info['class'] . '::' . $annotion_info['method'];
            $this->method_annotations[$key] = $annotion_info['annotation'];
        }
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $relative_uri = $request->getUri()->getPath();
        $api_forbidden_uris = ConfigUtil::getCommonConfig('api_forbidden', []);
        foreach ($api_forbidden_uris as $api_forbidden_uri) {
            if (stripos($relative_uri, $api_forbidden_uri) === 0) {
                $response = Context::get(ResponseInterface::class);
                return $response->withBody(new SwooleStream(json_encode([
                    'message' => 'api forbidden',
                    'code' => 403,
                ])));
            }
        }

        $query_params = $request->getQueryParams();
        $should_check_sign = false;
        /** @var Dispatched $dispatched */
        $dispatched = $request->getAttribute(Dispatched::class);
        if ($dispatched->isFound() && is_array($dispatched->handler->callback)) {
            $class = $dispatched->handler->callback[0] ?? null;
            $method = $dispatched->handler->callback[1] ?? null;
            if (!empty($class) && !empty($method)) {
                $method_annotation_key = $class . '::' . $method;
                /** @var SignAuth $annotion */
                $annotion = $this->method_annotations[$method_annotation_key] ?? null;
                if (empty($annotion)) {
                    $annotion = $this->class_annotations[$class] ?? null;
                }
                if (!empty($annotion)) {
                    $skip_sign_auth_flag = $query_params['web_flag'] ?? '';
                    if ($annotion->skip_sign_auth_flag && $annotion->skip_sign_auth_flag === $skip_sign_auth_flag) {
                        $should_check_sign = false;
                    } elseif ($annotion->check_sign) {
                        $should_check_sign = true;
                    }
                }
            }
        }
        if (HttpRequest::getNoSignCheck() === 'TRUE') {
            $should_check_sign = false;
        }
        $ip = ClientInfoUtil::remoteAddress();
        if ($ip == '127.0.0.1' || $ip == '::1') { // don't do sign check for localhost access
            $should_check_sign = false;
        }

        if ($should_check_sign) {
            $sign_in_query = $query_params['sign'] ?? '';
            if (empty($sign_in_query) || !in_array($sign_in_query, $this->getVaildSignListOfApiRequest($relative_uri, $query_params))) {
                $response = Context::get(ResponseInterface::class);
                return $response->withBody(new SwooleStream(json_encode([
                    'message' => 'sign error',
                    'code' => 403,
                ])));
            }
        }
        return $handler->handle($request);
    }

    private function getVaildSignListOfApiRequest($relative_uri, $params)
    {
        unset($params['sign'], $params['callback']);

        foreach ($params as $k => $v) {
            // if the value is array type,
            if (gettype($v) == 'array') {
                foreach ($v as $kk => $vv) {
                    $kk = $k . '[' . $kk . ']';
                    $params[$kk] = $vv;
                }
                unset($params[$k]);
            }
        }
        ksort($params);

        $params_string = '';
        foreach ($params as $k => $v) {
            if (empty($params_string)) {
                $params_string = rawurlencode($k) . '=' . rawurlencode($v);
            } else {
                $params_string .= '&' . rawurlencode($k) . '=' . rawurlencode($v);
            }
        }
        $sign_check_string_candidates = [];
        $sign_check_string = $relative_uri . $params_string . self::SIGN_SECRET;
        $sign_check_string_candidates[] = $sign_check_string;
        $replacements = [
            '%28' => '(',
            '%29' => ')',
            '%5B' => '[',
            '%5D' => ']',
            '%2F' => '/',
            '%27' => "'",
            '%21' => '!',
            '%20' => '+',
            '%3F' => '?',
            '%7E' => '~',
            '%2C' => ',',
            '%2A' => '*',
        ];
        foreach ($replacements as $search => $replacement) {
            $replaced_candidates = [];
            foreach ($sign_check_string_candidates as $str) {
                $replaced_candidates[] = str_replace($search, $replacement, $str);
            }
            $sign_check_string_candidates = array_unique(array_merge($sign_check_string_candidates, $replaced_candidates));
        }
        $valid_sign_list = [];
        foreach (array_unique($sign_check_string_candidates) as $sign_check_string) {
            $valid_sign_list[] = md5($sign_check_string);
        }
        return $valid_sign_list;
    }
}
