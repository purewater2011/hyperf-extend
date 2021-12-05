<?php

declare(strict_types=1);
/**
 * This file is part of hyperf server projects.
 */
namespace Test\Cases;

use Hyperf\Extend\Utils\Http;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @coversNothing
 */
class HttpTest extends TestCase
{
    public function testSimpleGet()
    {
        $response = Http::get('https://www.baidu.com');
        $this->assertIsObject($response);
        $this->assertNotEmpty($response);
        $this->assertNotEmpty($response->getHeaders());
        $this->assertEquals($response->getStatusCode(), 200);
    }
}
