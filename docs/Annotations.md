# Annotations 自定义注解

-----

## 一.ApiAccessLog接口访问日志

***1.配置middlewares添加ApiAccessLogMiddleware***
```
file: config/autoload/middlewares.php
'http' => [
    Hyperf\Extend\Middleware\ApiAccessLogMiddleware::class,
],

```

***2.在需要添加日志的Controller添加注解***

```
/**
 * @Hyperf\Extend\Annotations\ApiAccessLog()
 */
class IndexController{}
```


## 二. RbacAuth权限授权

***1.配置middlewares添加AuthMiddleware***
```
file: config/autoload/middlewares.php
'http' => [
    Hyperf\Extend\Middleware\AuthMiddleware::class,
],

```

***2.在需要添加日志的Controller添加注解***
> skip_auth=true,可跳过验证
```
/**
 * @Hyperf\Extend\Annotations\RbacAuth()
 */
class IndexController{}
```



## 三. SignAuth签名授权

***1.配置middlewares添加SignMiddleware***
```
file: config/autoload/middlewares.php
'http' => [
    Hyperf\Extend\Middleware\SignMiddleware::class,
],

```

***2.在需要添加日志的Controller添加注解***

> 可配置skip_sign_auth_flag，在api请求时添加web_flag参数，可跳过签名，主要用于测试
```
/**
 * @Hyperf\Extend\Annotations\SignAuth()
 */
class IndexController{}
```
