<?php

declare(strict_types=1);
/**
 * This file is part of hyperf server projects.
 */
namespace Test\Cases\Utils;

use Hyperf\Extend\Test\Mocks\PHPFunctionsMock;
use Hyperf\Extend\Utils\Carbon;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @coversNothing
 */
class CarbonUtilTest extends TestCase
{
    use PHPFunctionsMock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->initPHPFunctionsMock();
    }

    public function testNowWithTimezone()
    {
        $mocked_timestamp = strtotime('2021-05-01');
        $this->mockTime($mocked_timestamp);
        $carbon_instance = Carbon::now(7);
        $this->assertEquals($mocked_timestamp, $carbon_instance->getTimestamp());
        $this->assertEquals('+07:00', $carbon_instance->getTimezone()->getName());
    }
}
