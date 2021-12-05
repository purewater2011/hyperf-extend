<?php

declare(strict_types=1);
/**
 * This file is part of hyperf server projects.
 */
namespace Hyperf\Extend\Exception;

use Hyperf\ExceptionHandler\Formatter\FormatterInterface;
use Hyperf\GrpcServer\Exception\GrpcException;
use Hyperf\GrpcServer\Exception\Handler\GrpcExceptionHandler;
use Hyperf\Logger\LoggerFactory;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;

class HyperfGrpcExceptionHandler extends GrpcExceptionHandler
{
    public function __construct(ContainerInterface $container)
    {
        $this->logger = $container->get(LoggerFactory::class)->get('exception-handler');
        $this->formatter = $container->get(FormatterInterface::class);
        parent::__construct($this->logger, $this->formatter);
    }

    public function handle(Throwable $throwable, ResponseInterface $response)
    {
        if ($throwable instanceof GrpcException) {
            $this->logger->warning($this->formatter->format($throwable));
        } else {
            $this->logger->error($this->formatter->format($throwable));
        }

        return $this->transferToResponse((int) $throwable->getCode(), $throwable->getMessage(), $response);
    }
}
