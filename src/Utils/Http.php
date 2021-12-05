<?php

declare(strict_types=1);
/**
 * This file is part of hyperf server projects.
 */
namespace Hyperf\Extend\Utils;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\RequestOptions;
use Hyperf\Guzzle\CoroutineHandler;
use Hyperf\Utils\ApplicationContext;
use Hyperf\Extend\Events\HttpRequestCompleted;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface;
use Swoole\Coroutine;

class Http
{
    const DEFAULT_HEADERS = [
        'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        'Accept-Charset' => 'GB2312,utf-8;q=0.7,*;q=0.7',
        'Accept-Encoding' => 'gzip,deflate',
        'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_13_3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/67.0.3381.0 Safari/537.36',
    ];

    public static function get($url, $headers = [], $follow = true, $timeout = 5): ResponseInterface
    {
        $client = self::createGuzzleClient();
        $options = [
            RequestOptions::HEADERS => array_replace(self::DEFAULT_HEADERS, $headers),
            RequestOptions::TIMEOUT => $timeout,
            RequestOptions::ALLOW_REDIRECTS => $follow,
        ];
        [$url, $options] = self::resolveAuthInUrl($url, $options);
        $options = self::addProxyToOptions($url, $options);
        return $client->get($url, $options);
    }

    public static function post($url, $data, $headers = [], $follow = true, $timeout = 5): ResponseInterface
    {
        $body = (is_array($data)) ? http_build_query($data) : $data;
        $client = self::createGuzzleClient();
        $options = [
            RequestOptions::HEADERS => array_replace(self::DEFAULT_HEADERS, [
                'Content-Type' => 'application/x-www-form-urlencoded',
            ], $headers),
            RequestOptions::TIMEOUT => $timeout,
            RequestOptions::ALLOW_REDIRECTS => $follow,
            RequestOptions::BODY => $body,
        ];
        [$url, $options] = self::resolveAuthInUrl($url, $options);
        $options = self::addProxyToOptions($url, $options);
        return $client->post($url, $options);
    }

    public static function createGuzzleClient(): Client
    {
        if (Coroutine::getCid() > 0) {
            $handler_stack = HandlerStack::create(new CoroutineHandler());
            $handler_stack->push(self::getEventHandler());
            $options = ['handler' => $handler_stack];
            return new Client($options);
        }
        return new Client();
    }

    private static function getEventHandler(): callable
    {
        return function (callable $handler) {
            return function ($request, array $options) use ($handler) {
                $request_start_time = microtime(true);
                return $handler($request, $options)
                    ->then(function ($response) use ($request, $request_start_time) {
                        /** @var \GuzzleHttp\Psr7\Request $request */
                        /** @var \GuzzleHttp\Psr7\Response $response */
                        $event = new HttpRequestCompleted($request, $response);
                        $event->time = (microtime(true) - $request_start_time) * 1000;
                        /** @var EventDispatcherInterface $dispatcher */
                        $dispatcher = ApplicationContext::getContainer()->get(EventDispatcherInterface::class);
                        $dispatcher->dispatch($event);
                        return $response;
                    });
            };
        };
    }

    private static function resolveAuthInUrl($url, $options)
    {
        // 对 URL 地址中包含了用户登录验证的情况做兼容
        $uri = parse_url($url);
        if (!empty($uri['user'])) {
            $options[RequestOptions::AUTH] = [$uri['user'], $uri['pass']];
            $url = $uri['scheme'] . '://' . $uri['host'];
            if (!empty($uri['port'])) {
                $url .= ':' . $uri['port'];
            }
            if (!empty($uri['query'])) {
                $url .= '?' . $uri['query'];
            }
        }
        return [$url, $options];
    }

    private static function addProxyToOptions($url, $options)
    {
        if (in_array(parse_url($url)['host'], [
            'graph.facebook.com',
            'accounts.google.com',
            'admob.googleapis.com',
            'www.googleapis.com',
            'oauth2.googleapis.com',
            'fcm.googleapis.com',
            'storage.googleapis.com',
        ])) {
            if (ENVUtil::isDev()) {
                $options[RequestOptions::PROXY] = 'http://localhost:1087';
            } elseif (ENVUtil::isTest()) {
                $options['swoole'] = [
                    'socks5_host' => '127.0.0.1',
                    'socks5_port' => 1086,
                ];
            }
        }
        return $options;
    }
}
