<?php

declare(strict_types=1);
/**
 * This file is part of hyperf server projects.
 */
namespace Hyperf\Extend\Server\Events;

use Hyperf\HttpMessage\Server\Request as Psr7Request;
use Hyperf\Utils\Context;
use Hyperf\Extend\Server\HttpServer;
use Hyperf\Extend\Utils\Util;
use Hyperf\Extend\Utils\ClientInfoUtil;
use Psr\Http\Message\UriInterface;

class ResponseSendFailed
{
    /**
     * @var UriInterface
     */
    public $uri;

    /**
     * the start time of this request.
     * @var float
     */
    public $request_time;

    /**
     * the fail time of this request.
     * @var float
     */
    public $fail_time;

    /**
     * @var string
     */
    public $trace_id;

    /**
     * @var string
     */
    public $remote_addr;

    /**
     * @var string
     */
    public $caller_chain;

    public function __construct(Psr7Request $request)
    {
        $this->uri = $request->getUri();
        $this->fail_time = microtime(true);
        $this->request_time = Context::get(HttpServer::CONTEXT_KEY_REQUEST_TIME_FLOAT);
        $this->remote_addr = ClientInfoUtil::remoteAddress();
        $this->trace_id = Util::getTraceId();
        $this->caller_chain = $request->getHeaderLine('x-caller-chain') ?: null;
    }
}
