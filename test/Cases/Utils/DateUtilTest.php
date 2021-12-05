<?php

declare(strict_types=1);
/**
 * This file is part of hyperf server projects.
 */
namespace Test\Cases\Utils;

use Hyperf\Extend\Test\Mocks\PHPFunctionsMock;
use Hyperf\Extend\Utils\DateUtil;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @coversNothing
 */
class DateUtilTest extends TestCase
{
    use PHPFunctionsMock;

    public function setUp(): void
    {
        parent::setUp();
        $this->initPHPFunctionsMock();
    }

    public function testGetRealDate()
    {
        $this->assertEquals(date('Y-m-d'), DateUtil::getRealDate('today'));
        $this->assertEquals(date('Y-m-d', time() + 86400), DateUtil::getRealDate('tomorrow'));
        $this->assertEquals(date('Y-m-d', time() - 86400), DateUtil::getRealDate('yesterday'));
        $this->assertEquals(date('Y-m-d', time() - 86400 * 2), DateUtil::getRealDate('yesterday2'));
        $this->assertEquals(date('Y-m-d', time() - 86400 * 2), DateUtil::getRealDate(-2));
        $this->assertEquals(date('Y-m-d', time() + 86400 * 2), DateUtil::getRealDate(2));
        $this->assertEquals('2020-07-01', DateUtil::getRealDate('2020-07-01'));
    }

    public function testEveryday()
    {
        $dates = [];
        DateUtil::everyday('2020-07-01', '2020-07-01', function ($date) use (&$dates) {
            $dates[] = $date;
        });
        $this->assertEquals(['2020-07-01'], $dates);

        $dates = [];
        DateUtil::everyday('2020-07-01', '2020-07-02', function ($date) use (&$dates) {
            $dates[] = $date;
        });
        $this->assertEquals(['2020-07-01', '2020-07-02'], $dates);

        $dates = [];
        DateUtil::everyday('2020-06-29', '2020-07-02', function ($date) use (&$dates) {
            $dates[] = $date;
        });
        $this->assertEquals(['2020-06-29', '2020-06-30', '2020-07-01', '2020-07-02'], $dates);

        $dates = [];
        DateUtil::everyday('2021-03-03', '2021-03-01', function ($date) use (&$dates) {
            $dates[] = $date;
        });
        $this->assertEquals(['2021-03-03', '2021-03-02', '2021-03-01'], $dates);
    }

    public function testDateWithTimezone()
    {
        $format = 'Y-m-d H:i:s';
        $this->assertEquals(date($format), DateUtil::date($format));

        $timestamp = 1596637690;
        $this->assertEquals('2020-08-05 22:28:10', DateUtil::date($format, $timestamp));
        $this->assertEquals('2020-08-05 14:28:10', DateUtil::date($format, $timestamp, 0));
        $this->assertEquals('2020-08-05 15:28:10', DateUtil::date($format, $timestamp, 1));
        $this->assertEquals('2020-08-05 13:28:10', DateUtil::date($format, $timestamp, -1));
        $this->assertEquals('2020-08-05 06:28:10', DateUtil::date($format, $timestamp, -8));
    }

    public function testStrtotime()
    {
        $timestamp = 1596637690;
        $this->assertEquals($timestamp, DateUtil::strtotime('2020-08-05 22:28:10'));
        $this->assertEquals($timestamp, DateUtil::strtotime('2020-08-05 22:28:10', 8));
        $this->assertEquals($timestamp, DateUtil::strtotime('2020-08-05 14:28:10', 0));
        $this->assertEquals($timestamp, DateUtil::strtotime('2020-08-05 15:28:10', 1));
        $this->assertEquals($timestamp, DateUtil::strtotime('2020-08-05 13:28:10', -1));
        $this->assertEquals($timestamp, DateUtil::strtotime('2020-08-05 06:28:10', -8));
        $this->assertEquals(1596614400, DateUtil::strtotime('2020-08-05', -8));
        $this->assertEquals(time(), DateUtil::strtotime(DateUtil::date('Y-m-d H:i:s', time(), -8), -8));
    }

    public function testDateBeforeAfter()
    {
        $this->assertEquals('2020-01-31', DateUtil::dateBefore('2020-02-01', 1));
        $this->assertEquals('2020-02-02', DateUtil::dateBefore('2020-02-01', -1));
        $this->assertEquals('2021-01-01', DateUtil::dateAfter('2020-12-31', 1));
        $this->assertEquals('2020-12-30', DateUtil::dateAfter('2020-12-31', -1));
    }

    public function testTodayWithTimezone()
    {
        $this->mockTime(strtotime('2021-05-01'));
        $this->assertEquals('2021-04-30', DateUtil::today(7));
        $this->assertEquals('2021-05-01', DateUtil::today(8));
        $this->assertEquals('2021-04-30', DateUtil::yesterday(8));
        $this->assertEquals('2021-04-29', DateUtil::yesterday(7));

        $today_start_timestamp = DateUtil::strtotime(DateUtil::today(7), 7);
        $this->assertEquals('2021-04-30 01:00:00', date('Y-m-d H:i:s', $today_start_timestamp));
    }
}
