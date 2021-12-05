<?php

declare(strict_types=1);
/**
 * This file is part of hyperf server projects.
 */
namespace Hyperf\Extend\Server;

use FastRoute\Dispatcher;
use Google\Protobuf\Internal\Message;
use Hyperf\Contract\ConfigInterface;
use Hyperf\GrpcServer\Exception\GrpcException;
use Hyperf\HttpMessage\Stream\SwooleStream;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\CoreMiddleware;
use Hyperf\HttpServer\Router\Router;
use Hyperf\Logger\LoggerFactory;
use Hyperf\Utils\ApplicationContext;
use Hyperf\Utils\Str;
use Hyperf\Extend\Controller\Admin\DevController;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class HttpServerCoreMiddleware extends CoreMiddleware
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $this->response();
        try {
            return parent::process($request, $handler);
        } catch (GrpcException $e) {
            return $response->withAddedHeader('content-type', 'application/json')
                ->withBody(new SwooleStream(json_encode([
                    'message' => $e->getMessage(),
                    'code' => $e->getCode(),
                ])));
        } catch (\Hyperf\HttpMessage\Exception\NotFoundHttpException | \Hyperf\Di\Exception\NotFoundException $e) {
            return $response->withAddedHeader('content-type', 'application/json')
                ->withStatus(404)
                ->withBody(new SwooleStream(json_encode([
                    'message' => $e->getMessage(),
                    'code' => $e->getCode(),
                ])));
        }
    }

    protected function transferToResponse($response, ServerRequestInterface $request): ResponseInterface
    {
        if ($response instanceof Message) {
            return $this->response()
                ->withAddedHeader('content-type', 'application/json')
                ->withBody(new SwooleStream($response->serializeToJsonString(true)));
        }
        return parent::transferToResponse($response, $request);
    }

    protected function parseMethodParameters(string $controller, string $action, array $arguments): array
    {
        return $this->parseParameters($controller, $action, $arguments);
    }

    protected function parseParameters(string $controller, string $action, array $arguments): array
    {
        $injections = [];
        $definitions = $this->getMethodDefinitionCollector()->getParameters($controller, $action);
        foreach ($definitions ?? [] as $pos => $definition) {
            $value = $arguments[$pos] ?? $arguments[$definition->getMeta('name')] ?? null;
            if ($value === null) {
                if ($definition->getMeta('defaultValueAvailable')) {
                    $injections[] = $definition->getMeta('defaultValue');
                } elseif ($definition->allowsNull()) {
                    $injections[] = null;
                } elseif (is_subclass_of($definition->getName(), Message::class)) {
                    $injections[] = $this->createGrpcParamFromRequest($definition->getName());
                } elseif ($this->container->has($definition->getName())) {
                    $injections[] = $this->container->get($definition->getName());
                } else {
                    //parseParameters from request
                    $request_params = $this->getRequestParams();
                    $param_name = $definition->getMeta('name');
                    $field_name = $definition->getName();
                    if ($request_params && isset($request_params[$param_name])) {
                        if ($field_name == 'int' || $field_name == 'integer') {
                            $injections[] = intval($request_params[$param_name]);
                        } else {
                            $injections[] = $request_params[$param_name];
                        }
                    } else {
                        $message = "Parameter '{$definition->getMeta('name')}' of {$controller}::{$action} should not be null";
                        throw new \InvalidArgumentException($message);
                    }
                }
            } else {
                $injections[] = $this->getNormalizer()->denormalize($value, $definition->getName());
            }
        }

        return $injections;
    }

    protected function createDispatcher(string $serverName): Dispatcher
    {
        // TODO: 暂时把动态注入后台管理报表相关的路由代码放置在这里，因为没找到应该放置的位置
        // worker 启动之后再调用 addRoute 不会生效
        $config = ApplicationContext::getContainer()->get(ConfigInterface::class);
        // 增加 ping 响应支持，以便挂载在 SLB 下做服务监测
        Router::addRoute(['GET', 'HEAD'], '/ping', function () {
            return $this->response()->withBody(new SwooleStream('PONG'));
        });

        return parent::createDispatcher($serverName);
    }

    private function createGrpcParamFromRequest(string $class_name): Message
    {
        /** @var Message $instance */
        $instance = new $class_name();
        $request_params = $this->getRequestParams();
        if (!empty($request_params)) {
            if (extension_loaded('protobuf')) {
                // protobuf.so 扩展，内部函数实现与 php 库存在差异
                $instance->mergeFromJsonString(json_encode($request_params), true);
            } else {
                $pool = \Google\Protobuf\Internal\DescriptorPool::getGeneratedPool();
                $descriptor = $pool->getDescriptorByClassName(get_class($instance));
                foreach ($descriptor->getField() as $field) {
                    /** @var \Google\Protobuf\Internal\FieldDescriptor $field */
                    $field_name = $field->getName();
                    if (empty($request_params[$field_name])) {
                        continue;
                    }
                    $setter = $field->getSetter();
                    $value = $request_params[$field_name];
                    if ($field->getType() === 3) { // 预期是整形
                        $value = intval($value);
                    }
                    try {
                        $instance->{$setter}($value);
                    } catch (\Throwable $e) {
                        $logger = $this->container->get(LoggerFactory::class)->get('default');
                        $message = sprintf(
                            'got unexcepted value when convert request params to protobuf message in %s::%d.',
                            get_class($e),
                            $e->getFile(),
                            $e->getLine()
                        );
                        $logger->warning($message);
                    }
                }
            }
        }
        return $instance;
    }

    private function getRequestParams(): ?array
    {
        $request = $this->container->get(RequestInterface::class);
        $request_params = null;
        if ($request->isMethod(RequestMapping::GET)) {
            $request_params = $request->getQueryParams();
        } elseif ($request->isMethod(RequestMapping::POST)) {
            $request_params = $request->getParsedBody();
            $content_type = $request->getHeaderLine('Content-Type');
            if ($content_type && Str::startsWith($content_type, 'application/json')) {
                $request_params = json_decode($request->getBody()->getContents(), true);
            }
            $request_params = array_merge($request->getQueryParams(), $request_params);
        }
        return $request_params;
    }
}
