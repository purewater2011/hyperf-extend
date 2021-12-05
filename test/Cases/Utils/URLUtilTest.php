<?php

declare(strict_types=1);
/**
 * This file is part of hyperf server projects.
 */
namespace Test\Cases\Utils;

use Hyperf\Extend\Utils\URLUtil;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @coversNothing
 */
class URLUtilTest extends TestCase
{
    public function testAppendQueryParams()
    {
        $url = 'test://about';
        $this->assertEquals($url . '?a=b', URLUtil::appendQueryParams($url, ['a' => 'b']));

        $url = 'test://about?c=d';
        $this->assertEquals($url . '&a=b', URLUtil::appendQueryParams($url, ['a' => 'b']));

        $url = 'test://about?c=d&';
        $this->assertEquals($url . 'a=b', URLUtil::appendQueryParams($url, ['a' => 'b']));

        $url = 'test://about';
        $this->assertEquals($url . '?a=b&c=d', URLUtil::appendQueryParams($url, ['a' => 'b', 'c' => 'd']));
    }
}
