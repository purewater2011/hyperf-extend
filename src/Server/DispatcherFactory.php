<?php

declare(strict_types=1);
/**
 * This file is part of hyperf server projects.
 */
namespace Hyperf\Extend\Server;

use Hyperf\Di\Exception\ConflictAnnotationException;
use Hyperf\Di\ReflectionManager;
use Hyperf\HttpServer\Annotation\AutoController;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\MiddlewareManager;
use Hyperf\Utils\Str;
use ReflectionMethod;

class DispatcherFactory extends \Hyperf\HttpServer\Router\DispatcherFactory
{
    protected function initAnnotationRoute(array $collector): void
    {
        foreach ($collector as $className => $metadata) {
            if (isset($metadata['_c'][AutoController::class])) {
                if ($this->hasControllerAnnotation($metadata['_c'])) {
                    $message = sprintf('AutoController annotation can\'t use with Controller annotation at the same time in %s.', $className);
                    throw new ConflictAnnotationException($message);
                }
                $middlewares = $this->handleMiddleware($metadata['_c']);
                $this->handleAutoController($className, $metadata['_c'][AutoController::class], $middlewares, $metadata['_m'] ?? []);
            }
            if (isset($metadata['_c'][GrpcAutoController::class])) {
                if ($this->hasControllerAnnotation($metadata['_c'])) {
                    $message = sprintf('AutoController annotation can\'t use with Controller annotation at the same time in %s.', $className);
                    throw new ConflictAnnotationException($message);
                }
                $middlewares = $this->handleMiddleware($metadata['_c']);
                $this->handleAutoController($className, $metadata['_c'][GrpcAutoController::class], $middlewares, $metadata['_m'] ?? []);
            }
            if (isset($metadata['_c'][Controller::class])) {
                $middlewares = $this->handleMiddleware($metadata['_c']);
                $this->handleController($className, $metadata['_c'][Controller::class], $metadata['_m'] ?? [], $middlewares);
            }
        }
    }

    /**
     * Register route according to AutoController annotation.
     */
    protected function handleAutoController(string $className, AutoController $annotation, array $middlewares = [], array $methodMetadata = []): void
    {
        $class = ReflectionManager::reflectClass($className);
        $methods = $class->getMethods(ReflectionMethod::IS_PUBLIC);
        $prefix = $this->getPrefix($className, $annotation->prefix);
        $router = $this->getRouter($annotation->server);

        $autoMethods = ['GET', 'POST', 'HEAD', 'OPTIONS'];
        $defaultAction = '/index';
        foreach ($methods as $method) {
            $path = $this->parsePath($prefix, $method);
            $methodName = $method->getName();
            if (substr($methodName, 0, 2) === '__') {
                continue;
            }
            $router->addRoute($autoMethods, $path, [$className, $methodName, $annotation->server]);

            $methodMiddlewares = $middlewares;
            // Handle method level middlewares.
            if (isset($methodMetadata[$methodName])) {
                $methodMiddlewares = array_merge($methodMiddlewares, $this->handleMiddleware($methodMetadata[$methodName]));
                $methodMiddlewares = array_unique($methodMiddlewares);
            }

            // Register middlewares.
            foreach ($autoMethods as $autoMethod) {
                MiddlewareManager::addMiddlewares($annotation->server, $path, $autoMethod, $methodMiddlewares);
            }
            if (Str::endsWith($path, $defaultAction)) {
                $path = Str::replaceLast($defaultAction, '', $path);
                $router->addRoute($autoMethods, $path, [$className, $methodName, $annotation->server]);
                foreach ($autoMethods as $autoMethod) {
                    MiddlewareManager::addMiddlewares($annotation->server, $path, $autoMethod, $methodMiddlewares);
                }
            }
        }
    }
}
