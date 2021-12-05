# 自定义事件及监听
>以下自定义事件的设计思路可以借鉴，开发自己想要的自定义事件
-----

## 一.慢请求事件【Hyperf\Extend\Server\Events\SlowRequest】
> 在api和微服务调用中，经常出现慢请求情况。定义该事件，会在每个请求周期里监听超过1s的请求。配置好后，会将慢日志打印到日志文件里
```
配置：
    file: config/autoload/server.php
    配置：
'callbacks' => [
Event::ON_REQUEST => [Hyperf\Extend\Server\HttpServer::class, 'onRequest'],
],

```

## 二.响应失败事件【Hyperf\Extend\Server\Events\ResponseSendFailed】
> 在每次请求响应，如果出现异常，将打印失败响应日志到日志文件里。配置同一
 
## 三.Http完成请求事件【Hyperf\Extend\Events\HttpRequestCompleted】
> 指调用第三方请求，完成后触发的事件。只有使用Hyperf\Extend\Utils\Http的调用，才会触发。

## 四.redis完成执行事件【Hyperf\Extend\Events\RedisQueryExecuted】
> 每次redis调用完成后，触发完成事件。