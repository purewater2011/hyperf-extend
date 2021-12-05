<?php

declare(strict_types=1);
/**
 * This file is part of hyperf server projects.
 */
namespace Test\Cases\Model;

use Hyperf\Extend\Constant;
use Hyperf\Extend\Model\CsvTableModel;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @coversNothing
 */
class CsvTableModelTest extends TestCase
{
    public function testMerge()
    {
        $csv1 = new CsvTableModel();
        $csv1->headers = ['a', 'b'];
        $csv1->rows[] = [1, 2];
        $csv2 = new CsvTableModel();
        $csv2->headers = ['a', 'c'];
        $csv2->rows[] = [3, 4];
        $csv2->rows[] = [1, 5];
        $csv1->merge($csv2);
        $this->assertEquals([1, 2, 5], $csv1->rows[0]);
        $this->assertEquals([3, null, 4], $csv1->rows[1]);

        $csv1->merge($csv2, CsvTableModel::MERGE_TYPE_SUM);
        $this->assertEquals([1, 2, 10], $csv1->rows[0]);
        $this->assertEquals([3, null, 8], $csv1->rows[1]);

        $csv1->merge($csv2, CsvTableModel::MERGE_TYPE_MIN);
        $this->assertEquals([1, 2, 5], $csv1->rows[0]);
        $this->assertEquals([3, null, 4], $csv1->rows[1]);

        $csv2->rows[] = [1, 15];
        $csv2->rows[] = [3, 99];
        $csv1->merge($csv2, CsvTableModel::MERGE_TYPE_MAX);
        $this->assertEquals([1, 2, 15], $csv1->rows[0]);
        $this->assertEquals([3, null, 99], $csv1->rows[1]);
    }

    public function testGroupMerge()
    {
        $csv1 = new CsvTableModel();
        $csv1->headers = ['a', 'b', 'c'];
        $csv1->rows[] = [1, 2, 3];
        $csv2 = new CsvTableModel();
        $csv2->headers = ['a', 'b', 'c'];
        $csv2->rows[] = [1, 2, 4];
        $csv2->rows[] = [1, 3, 4];
        $csv1->merge($csv2, CsvTableModel::MERGE_TYPE_SUM, 2);
        $this->assertEquals([1, 2, 7], $csv1->rows[0]);
        $this->assertEquals([1, 3, 4], $csv1->rows[1]);
    }

    public function testExpand()
    {
        $csv = new CsvTableModel();
        $csv->headers = ['日期', '国家', '人数'];
        $csv->rows[] = ['2020-01-01', '中国', 100];
        $csv->rows[] = ['2020-01-04', '韩国', 2];
        $csv->rows[] = ['2020-01-02', '美国', 99];
        $csv->rows[] = ['2020-01-01', '日本', 9];
        $csv->rows[] = ['2020-01-04', '韩国', 3];
        $csv->expand('国家', '人数');
        $this->assertCount(5, $csv->headers);
        $this->assertEquals(['日期', '中国', '韩国', '美国', '日本'], $csv->headers);
        $this->assertEquals(['2020-01-01', 100, null, null, 9], $csv->rows[0]);
        $this->assertEquals(['2020-01-04', null, 5, null, null], $csv->rows[1]);
        $this->assertEquals(['2020-01-02', null, null, 99, null], $csv->rows[2]);

        // 测试余下两列的情况
        $csv = new CsvTableModel();
        $csv->headers = ['日期', '语言', '国家', '人数'];
        $csv->rows[] = ['2020-01-01', '中文', '中国', 100];
        $csv->rows[] = ['2020-01-01', '中文', '印尼', 9];
        $csv->rows[] = ['2020-01-01', '英文', '中国', 8];
        $csv->rows[] = ['2020-01-04', '英文', '印尼', 5];
        $csv->expand('国家', '人数');
        $this->assertEquals(['日期', '语言', '中国', '印尼'], $csv->headers);
        $this->assertEquals(['2020-01-01', '中文', 100, 9], $csv->rows[0]);
        $this->assertEquals(['2020-01-01', '英文', 8, null], $csv->rows[1]);
        $this->assertEquals(['2020-01-04', '英文', null, 5], $csv->rows[2]);
    }

    public function testAddColumn()
    {
        $csv = new CsvTableModel();
        $csv->headers = ['日期', '国家', '新增', '次留'];
        $csv->rows[] = ['2020-01-01', '中国', 100, 10];
        $csv->rows[] = ['2020-01-01', '印度', 1000, 202];
        $csv->addColumnPercentage('D1', '次留', '新增');
        $this->assertEquals(['日期', '国家', '新增', '次留', 'D1'], $csv->headers);
        $this->assertEquals(['2020-01-01', '中国', 100, 10, '10.00%'], $csv->rows[0]);
        $this->assertEquals(['2020-01-01', '印度', 1000, 202, '20.20%'], $csv->rows[1]);

        $csv->addColumnSum('新增+次留', ['新增', '次留']);
        $this->assertEquals(['2020-01-01', '中国', 100, 10, '10.00%', 110], $csv->rows[0]);
        $this->assertEquals(['2020-01-01', '印度', 1000, 202, '20.20%', 1202], $csv->rows[1]);

        $csv->removeColumn('次留');
        $this->assertEquals(['日期', '国家', '新增', 'D1', '新增+次留'], $csv->headers);
        $this->assertEquals(['2020-01-01', '中国', 100, '10.00%', 110], $csv->rows[0]);
        $this->assertEquals(['2020-01-01', '印度', 1000, '20.20%', 1202], $csv->rows[1]);

        $csv->removeColumn('新增+次留');
        $csv->removeColumn('D1');
        $csv->addColumn('日活', function ($row) use ($csv) {
            return $csv->cell($row, '新增') * 2;
        }, -1);
        $this->assertEquals(['2020-01-01', '中国', 200, 100], $csv->rows[0]);
        $this->assertEquals(['2020-01-01', '印度', 2000, 1000], $csv->rows[1]);
    }

    public function testReplaceHeaders()
    {
        $csv = new CsvTableModel();
        $csv->headers = ['日期', '国家', '-1', '1'];
        $csv->rows[] = ['2020-01-01', '中国', 100, 10];
        $csv->replaceHeaders([-1 => '新增', 1 => '次留']);
        $this->assertEquals(['日期', '国家', '新增', '次留'], $csv->headers);
        $this->assertEquals(['2020-01-01', '中国', 100, 10], $csv->rows[0]);
    }

    public function testSwapColumns()
    {
        $csv = new CsvTableModel();
        $csv->headers = ['日期', 'APP', '人数'];
        $csv->rows[] = ['2020-01-01', 'APP_1', 110];
        $csv->rows[] = ['2020-01-01', 'APP_2', 220];
        $csv->swapColumns('APP', '人数');
        $this->assertEquals(['日期', '人数', 'APP'], $csv->headers);
        $this->assertEquals(['2020-01-01', 110, 'APP_1'], $csv->rows[0]);
        $this->assertEquals(['2020-01-01', 220, 'APP_2'], $csv->rows[1]);
    }

    public function testMoveColumns()
    {
        $csv = new CsvTableModel();
        $csv->headers = ['日期', 'APP', '人数'];
        $csv->rows[] = ['2020-01-01', 'APP_1', 110];
        $csv->rows[] = ['2020-01-01', 'APP_2', 220];
        $csv->moveColumn('APP', 0);
        $this->assertEquals(['APP', '日期', '人数'], $csv->headers);
        $this->assertEquals(['APP_1', '2020-01-01', 110], $csv->rows[0]);
        $this->assertEquals(['APP_2', '2020-01-01', 220], $csv->rows[1]);
    }

    public function testAddLink()
    {
        $csv = new CsvTableModel();
        $csv->headers = ['作品id', '标题'];
        $csv->rows[] = [1, '总裁'];
        $csv->rows[] = [2, '纯情'];
        $this->assertEquals(['作品id', '标题'], $csv->headers);
    }
}
