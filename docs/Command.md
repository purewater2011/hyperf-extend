# 自定义命令行

-----

## 一.单文件支持多命令类
> hyperf官方的Command，只支持一个命令一个类文件。但实际的使用场景中，会有多个命令一个文件的情况

```
使用方法：定义的Command继承MultiActionBaseCommand，方法名以Action结尾
示例：
/**
 * @Command
 */
class TestCommand extends MultiActionBaseCommand
{
    protected $name = 'test';

    public function ttAction($a)
    {
    }
}

执行：php bin/hyperf.php test tt --a=1

```

## 2.自定义命令行

***1.Mysql数据导出***
> 支持将mysql的数据直接导出到文件，支持256、1024分表的聚合导出
```
a.单表导出
php bin/hyperf data-export:mysql exportTable --table=$tablename --pool=$dbname

b.单表指定日期导出
php bin/hyperf data-export:mysql exportTableOnDate --table=$tablename --pool=$dbname --date='yesterday' --field='created_at'

c.分表256导出
php bin/hyperf data-export:mysql exportTableSplit256 --table=$tablename --pool=$dbname

d.分表256指定日期导出
php bin/hyperf data-export:mysql exportTableSplit256OnDate --table=$tablename --pool=$dbname --date='yesterday' --field='created_at'

```

***2.redis数据导出***
>主要是把redis指定库的数据导出

```
a.key => value 类型数据导出
php bin/hyperf data-export:redis export --pool=$dbname --database=$dbselect --pattern='*'

b.List类型数据导出
php bin/hyperf data-export:redis exportList --pool=$dbname --database=$dbselect --pattern='*'

c.ZSet类型数据导出
php bin/hyperf data-export:redis exportZSet --pool=$dbname --database=$dbselect --pattern='*'

```