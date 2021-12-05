<?php

declare(strict_types=1);
/**
 * This file is part of hyperf server projects.
 */
namespace Hyperf\Extend\Controller;

use Hyperf\Contract\LengthAwarePaginatorInterface;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface;
use Hyperf\HttpServer\Request;
use Hyperf\HttpServer\Response;
use Hyperf\Logger\LoggerFactory;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use stdClass;

abstract class HttpBaseController
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @var Request
     */
    protected $request;

    /**
     * @var Response
     */
    protected $response;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    public function __construct(ContainerInterface $container, LoggerFactory $loggerFactory)
    {
        $this->container = $container;
        $this->request = $container->get(RequestInterface::class);
        $this->response = $container->get(ResponseInterface::class);
        $this->logger = $loggerFactory->get('default');
    }

    public function formatPaginatorForJSONOutput(LengthAwarePaginatorInterface $paginator): stdClass
    {
        $page_count = floor($paginator->total() / $paginator->perPage());
        if ($paginator->total() % $paginator->perPage() !== 0) {
            ++$page_count;
        }
        $next_page = $paginator->hasMorePages() ? $paginator->currentPage() + 1 : 0;
        $json = new stdClass();
        $json->total_count = $paginator->total();
        $json->items_count_per_page = $paginator->perPage();
        $json->page_count = $page_count;
        $json->next_page = $next_page;
        return $json;
    }

    public function setRequest(RequestInterface $request)
    {
        $this->request = $request;
    }

    protected function writeSuccessJsonResponse($data = null, $extra_data = [])
    {
        return $this->writeJsonResponse($data, 'success', '', $extra_data);
    }

    protected function writeErrorJsonResponse($message = '', $error_code = false)
    {
        if ($error_code === false) {
            return $this->writeJsonResponse(null, 'error', $message, []);
        }
        return $this->writeJsonResponse(null, 'error', $message, ['error_code' => $error_code]);
    }

    protected function writeErrorJsonResponseCaseParamsError()
    {
        return $this->writeErrorJsonResponse('params error');
    }

    protected function writeErrorJsonResponseCaseAccessDenied($message = '')
    {
        return $this->writeErrorJsonResponse('access denied:' . $message);
    }

    private function writeJsonResponse($data, $status, $message = '', $extra_data = [])
    {
        $result = [
            'status' => $status,
            'timestamp' => time(),
        ];
        if ($data !== null) {
            $result['data'] = $data;
        }
        if ($message) {
            $result['message'] = $message;
        }
        if ($extra_data) {
            foreach ($extra_data as $key => $value) {
                if (is_null($value)) {
                    continue;
                }
                $result[$key] = $value;
            }
        }
        return $result;
    }
}
