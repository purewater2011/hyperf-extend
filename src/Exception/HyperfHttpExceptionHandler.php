<?php

declare(strict_types=1);
/**
 * This file is part of hyperf server projects.
 */
namespace Hyperf\Extend\Exception;

use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\ExceptionHandler\Formatter\FormatterInterface;
use Hyperf\HttpMessage\Exception\HttpException;
use Hyperf\HttpMessage\Stream\SwooleStream;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Exception\Handler\HttpExceptionHandler;
use Hyperf\Logger\LoggerFactory;
use Hyperf\Extend\Utils\ENV;
use Hyperf\Extend\Utils\Util;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;

class HyperfHttpExceptionHandler extends HttpExceptionHandler
{
    private $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->logger = $container->get(LoggerFactory::class)->get('exception-handler');
        $this->formatter = $container->get(FormatterInterface::class);
        parent::__construct($this->logger, $this->formatter);
    }

    /**
     * Handle the exception, and return the specified result.
     * @param HttpException $throwable
     */
    public function handle(Throwable $throwable, ResponseInterface $response)
    {
        $this->writeThrowableLog($throwable);
        $message = $throwable->getMessage();
        if (ENV::isDev()) {
            $message .= "\n" . $throwable->getTraceAsString();
        }
        return parent::handle($throwable, $response)->withStatus($throwable->getStatusCode())
            ->withAddedHeader('content-type', 'application/json')
            ->withBody(new SwooleStream(json_encode([
                'code' => $throwable->getStatusCode(),
                'message' => $message,
            ])));
    }

    protected function writeThrowableLog(Throwable $throwable)
    {
        /** @var RequestInterface $request */
        $request = $this->container->get(RequestInterface::class);
        if (ENV::isDev()) {
            /** @var StdoutLoggerInterface $std_logger */
            $std_logger = $this->container->get(StdoutLoggerInterface::class);
            $std_logger->error($request->getMethod() . ' ' . $request->fullUrl());
            $std_logger->error($this->formatter->format($throwable));
        }
        $message = $request->getMethod() . ' ' . $request->fullUrl() . "\n" . $this->formatter->format($throwable);
        $trace_id = Util::getTraceId();
        if (!empty($trace_id)) {
            $message = $trace_id . ' ' . $message;
        }
        $this->logger->error($message);
    }
}
