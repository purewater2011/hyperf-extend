# BigData 大数据【目前主要是clickhouse】

-----

## 功能介绍

clickhouse的基础使用

***1.添加配置config/autoload/clickhouse.php***
```
示例格式：
return [
    'default' => [
        'host' => $host,
        'port' => 8123,
        'username' => 'username',
        'password' => 'password',
    ]
];
```
***2.使用示例***
```
$rows = Hyperf\Extend\BigData\CKBaseModel::db($table, $pool = 'default')->select($sql, $params)->rows()

```

***3.工具类 Hyperf\Extend\Utils\ClickHouse