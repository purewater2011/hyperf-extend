<?php

declare(strict_types=1);
/**
 * This file is part of hyperf server projects.
 */
namespace Test\Cases\Utils;

use Hyperf\Extend\Utils\Aliyun\OSSUtil;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @coversNothing
 */
class ClickHouseTest extends TestCase
{
    public function testShowTables()
    {
        $this->assertTrue(true);
//        var_dump(ClickHouseUtil::showTables('default', 'push'));
//        var_dump(ClickHouseUtil::showCreateTable('default', 'push', 'send_logs'));
//        var_dump(ClickHouseUtil::showTableFieldTypes('default', 'push', 'send_logs'));
    }

    public function testImportUrl()
    {
        $this->assertTrue(true);

//        $url = OSSUtil::signUrlHangzhou('sls-exported-logs', 'push-send-formatted/2021-04-04.tsv.gz', 3600);
//        $url = 'http:' . substr($url, 6);
//        $this->assertTrue(true);
//        var_dump(ClickHouseUtil::importUrlToClickHouse(
//            $url, 'default', 'push', 'send_logs',
//            'TabSeparatedWithNames', []
//        ));
    }
}
