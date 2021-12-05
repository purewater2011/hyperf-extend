<?php

declare(strict_types=1);
/**
 * This file is part of hyperf server projects.
 */
namespace Test\Cases\Utils;

use Hyperf\Extend\Utils\ArrayUtil;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @coversNothing
 */
class ArrayUtilTest extends TestCase
{
    public function testGroup()
    {
        $this->assertIsArray(ArrayUtil::group([], 1));
        $this->assertIsArray(ArrayUtil::group([1], 1));
        $this->assertIsArray(ArrayUtil::group([1], 10));
        $this->assertEquals([[1], [2], [3]], ArrayUtil::group([1, 2, 3], 1));
        $this->assertEquals([[1, 2, 3]], ArrayUtil::group([1, 2, 3], 3));
        $this->assertEquals([[1, 2, 3], [4]], ArrayUtil::group([1, 2, 3, 4], 3));
        $this->assertEquals([[1, 2, 3], [4, 5]], ArrayUtil::group([1, 2, 3, 4, 5], 3));
        $this->assertEquals([[1, 2, 3], [4, 5, 6]], ArrayUtil::group([1, 2, 3, 4, 5, 6], 3));
        $this->assertEquals([[1, 2, 3], [4, 5, 6], [7]], ArrayUtil::group([1, 2, 3, 4, 5, 6, 7], 3));
        $this->assertEquals([[1, 2, 3, 4], [5, 6, 7]], ArrayUtil::group([1, 2, 3, 4, 5, 6, 7], 4));
    }

    public function testConvertToIntArray()
    {
        $this->assertIsArray(ArrayUtil::convertToIntArray([]));
        $this->assertEquals([1, 2], ArrayUtil::convertToIntArray([1, 2]));
        $this->assertEquals([0, 1], ArrayUtil::convertToIntArray(['aa', 1]));
        $this->assertEquals([1, 2], ArrayUtil::convertToIntArray([1, 2, 1], true));
        $this->assertEquals([1], ArrayUtil::convertToIntArray([1, 2, 3], false, '=', 1));
        $this->assertEquals([2, 3], ArrayUtil::convertToIntArray([1, 2, 3], false, '!=', 1));
        $this->assertEquals([3], ArrayUtil::convertToIntArray([1, 2, 3], false, '>', 2));
        $this->assertEquals([2, 3], ArrayUtil::convertToIntArray([1, 2, 3], false, '>=', 2));
        $this->assertEquals([1], ArrayUtil::convertToIntArray([1, 2, 3], false, '<', 2));
        $this->assertEquals([1, 2], ArrayUtil::convertToIntArray([1, 2, 3], false, '<=', 2));
        $this->assertEquals([1, 2], ArrayUtil::convertToIntArray([1, 2, 3, 1, 2], true, '<=', 2));
    }
}
