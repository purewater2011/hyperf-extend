<?php

declare(strict_types=1);
/**
 * This file is part of hyperf server projects.
 */
namespace Hyperf\Extend\Events;

class HttpRequestCompleted
{
    /**
     * The number of milliseconds it took to execute the request.
     * @var float
     */
    public $time;

    /**
     * @var \GuzzleHttp\Psr7\Request
     */
    private $request;

    /**
     * @var \GuzzleHttp\Psr7\Response
     */
    private $response;

    public function __construct($request, $response)
    {
        $this->request = $request;
        $this->response = $response;
    }

    public function getResponseLength()
    {
        return $this->response ? $this->response->getBody()->getSize() : 0;
    }

    public function getRequestUrl(): string
    {
        return (string) $this->request->getUri();
    }
}
